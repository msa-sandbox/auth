<?php

declare(strict_types=1);

namespace App\Tests\Functional\Http;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
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

        // Let's clean the user table
        $this->em->createQuery('DELETE FROM App\Entity\User u')->execute();
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
        $user = $this->createUser('test_'.strtolower($role).'@example.com', [$role]);

        return $this->jwtManager->createFromPayload($user, ['id' => $user->getId()]);
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
        $targetUser = $this->createUser('target@example.com', ['ROLE_USER']);

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
        $targetUser = $this->createUser('target2@example.com', ['ROLE_USER']);

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
}
