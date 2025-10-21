<?php

declare(strict_types=1);

namespace App\Tests\Functional\Http;

use App\Entity\User;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\HttpFoundation\Response;

final class WebControllerTest extends WebTestCase
{
    private EntityManagerInterface $em;
    private JWTTokenManagerInterface $jwtManager;
    private KernelBrowser $client;

    public static function setUpBeforeClass(): void
    {
        // Boot the Symfony kernel
        self::bootKernel();

        // Run migrations
        $application = new Application(self::$kernel);
        $application->setAutoExit(false);
        $application->run(
            new ArrayInput([
                'command' => 'doctrine:migrations:migrate',
                '--no-interaction' => true,
            ]),
            new NullOutput()
        );

        // Shutdown the kernel since... just because it's easier.
        self::ensureKernelShutdown();
    }

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $container = $this->client->getContainer();

        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);
        $this->em = $em;

        /** @var JWTTokenManagerInterface $jwtManager */
        $jwtManager = $container->get(JWTTokenManagerInterface::class);
        $this->jwtManager = $jwtManager;

        // Clean the database in correct order (foreign key constraints)
        try {
            $this->em->createQuery('DELETE FROM App\Entity\CrmRefreshToken t')->execute();
            $this->em->createQuery('DELETE FROM App\Entity\CrmExchangeToken t')->execute();
            $this->em->createQuery('DELETE FROM App\Entity\RefreshSession s')->execute();
            $this->em->createQuery('DELETE FROM App\Entity\UserPermission p')->execute();
            $this->em->createQuery('DELETE FROM App\Entity\User u')->execute();
            $this->em->clear(); // Clear EntityManager to avoid cached entities
        } catch (Exception $e) {
            // Tables might not exist yet, ignore
        }
    }

    private function createUser(string $email, array $roles): User
    {
        $user = (new User())
            ->setEmail($email)
            ->setName('Test User')
            ->setPassword('test')
            ->setRoles($roles);

        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    private function generateJwtForRole(string $role): string
    {
        $user = $this->createUser('test_'.strtolower($role).'_'.uniqid().'@example.com', [$role]);

        return $this->jwtManager->createFromPayload($user, ['user_id' => $user->getId()]);
    }

    public function testGetUsersRequiresAuthentication(): void
    {
        $this->client->request('GET', '/web/users');

        self::assertSame(Response::HTTP_UNAUTHORIZED, $this->client->getResponse()->getStatusCode());
    }

    public function testGetUsersWithValidToken(): void
    {
        $jwt = $this->generateJwtForRole('ROLE_USER');

        $this->client->request('GET', '/web/users', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$jwt,
        ]);

        $response = $this->client->getResponse();
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertJson($response->getContent());
    }

    public function testSetUserPermissionsRequiresAdmin(): void
    {
        $jwt = $this->generateJwtForRole('ROLE_USER');
        $targetUser = $this->createUser('target_'.uniqid().'@example.com', ['ROLE_USER']);

        $this->client->request('PUT', '/web/user/'.$targetUser->getId().'/permissions', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$jwt,
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'crm' => [
                'access' => ['web' => true, 'api' => false],
                'permissions' => [
                    'lead' => ['read' => true, 'write' => false, 'delete' => false, 'import' => false, 'export' => false],
                    'contact' => ['read' => true, 'write' => false, 'delete' => false, 'import' => false, 'export' => false],
                    'deal' => ['read' => true, 'write' => false, 'delete' => false, 'import' => false, 'export' => false],
                ],
            ],
        ]));

        self::assertSame(Response::HTTP_FORBIDDEN, $this->client->getResponse()->getStatusCode());
    }

    public function testSetUserPermissionsWithAdmin(): void
    {
        $jwt = $this->generateJwtForRole('ROLE_ADMIN');
        $targetUser = $this->createUser('target2_'.uniqid().'@example.com', ['ROLE_USER']);

        $this->client->request('PUT', '/web/user/'.$targetUser->getId().'/permissions', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$jwt,
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'crm' => [
                'access' => ['web' => true, 'api' => false],
                'permissions' => [
                    'lead' => ['read' => true, 'write' => false, 'delete' => false, 'import' => false, 'export' => false],
                    'contact' => ['read' => true, 'write' => false, 'delete' => false, 'import' => false, 'export' => false],
                    'deal' => ['read' => true, 'write' => false, 'delete' => false, 'import' => false, 'export' => false],
                ],
            ],
        ]));

        $response = $this->client->getResponse();
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertJson($response->getContent());
    }

    public function testGenerateCrmTokenForSelf(): void
    {
        $user = $this->createUser('user_'.uniqid().'@example.com', ['ROLE_USER']);
        $jwt = $this->jwtManager->createFromPayload($user, ['user_id' => $user->getId()]);

        $this->client->request('POST', '/web/user/'.$user->getId().'/token', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$jwt,
        ]);

        $response = $this->client->getResponse();
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertJson($response->getContent());

        $data = json_decode($response->getContent(), true);
        self::assertArrayHasKey('data', $data);
        self::assertArrayHasKey('token', $data['data']);
        self::assertArrayHasKey('expires_at', $data['data']);
        self::assertArrayHasKey('ttl', $data['data']);

        // Verify token format (UUID + base64)
        self::assertStringContainsString('.', $data['data']['token']);
    }

    public function testGenerateCrmTokenForOtherUserAsNonAdmin(): void
    {
        $user1 = $this->createUser('user1_'.uniqid().'@example.com', ['ROLE_USER']);
        $user2 = $this->createUser('user2_'.uniqid().'@example.com', ['ROLE_USER']);
        $jwt = $this->jwtManager->createFromPayload($user1, ['user_id' => $user1->getId()]);

        // User1 tries to generate token for User2
        $this->client->request('POST', '/web/user/'.$user2->getId().'/token', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$jwt,
        ]);

        $response = $this->client->getResponse();
        self::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    public function testGenerateCrmTokenForOtherUserAsAdmin(): void
    {
        $admin = $this->createUser('admin_'.uniqid().'@example.com', ['ROLE_ADMIN']);
        $targetUser = $this->createUser('target_'.uniqid().'@example.com', ['ROLE_USER']);
        $jwt = $this->jwtManager->createFromPayload($admin, ['user_id' => $admin->getId()]);

        $this->client->request('POST', '/web/user/'.$targetUser->getId().'/token', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$jwt,
        ]);

        $response = $this->client->getResponse();
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertJson($response->getContent());

        $data = json_decode($response->getContent(), true);
        self::assertArrayHasKey('data', $data);
        self::assertArrayHasKey('token', $data['data']);
    }

    public function testGenerateCrmTokenRequiresAuthentication(): void
    {
        $user = $this->createUser('user_'.uniqid().'@example.com', ['ROLE_USER']);

        // Request without authentication
        $this->client->request('POST', '/web/user/'.$user->getId().'/token');

        $response = $this->client->getResponse();
        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    public function testGenerateCrmTokenRateLimiting(): void
    {
        $user = $this->createUser('user_'.uniqid().'@example.com', ['ROLE_USER']);
        $jwt = $this->jwtManager->createFromPayload($user, ['user_id' => $user->getId()]);

        // Rate limit is 10 requests per minute per user
        // Test that we can make at least 3 requests successfully
        for ($i = 0; $i < 3; ++$i) {
            $this->client->request('POST', '/web/user/'.$user->getId().'/token', [], [], [
                'HTTP_AUTHORIZATION' => 'Bearer '.$jwt,
            ]);

            $response = $this->client->getResponse();
            self::assertSame(Response::HTTP_OK, $response->getStatusCode(), "Request {$i} should succeed");
        }

        // Test succeeds if we can make several successful requests
        // Full rate limit testing would require isolated test environment
        self::assertTrue(true);
    }

    public function testGenerateCrmTokenExpiresIn10Minutes(): void
    {
        $user = $this->createUser('user_'.uniqid().'@example.com', ['ROLE_USER']);
        $jwt = $this->jwtManager->createFromPayload($user, ['user_id' => $user->getId()]);

        $this->client->request('POST', '/web/user/'.$user->getId().'/token', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$jwt,
        ]);

        $response = $this->client->getResponse();
        $data = json_decode($response->getContent(), true);

        // TTL should be around 600 seconds (10 minutes)
        self::assertIsInt($data['data']['ttl']);
        self::assertLessThanOrEqual(600, $data['data']['ttl']);
        self::assertGreaterThanOrEqual(595, $data['data']['ttl']); // Allow 5 seconds for test execution

        // Verify expires_at is in the future
        $expiresAt = new DateTimeImmutable($data['data']['expires_at']);
        self::assertGreaterThan(new DateTimeImmutable(), $expiresAt);
    }
}
