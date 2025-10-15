<?php

namespace App\DataFixtures;

use App\Entity\User;
use App\Security\Roles;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[Group('users')]
class UserFixtures extends Fixture
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create();

        // Create an admin user
        $admin = new User();
        $admin->setName('Alice');
        $admin->setEmail('alice@a.net');
        $admin->setRoles([Roles::ROLE_ADMIN]);
        $admin->setPassword($this->passwordHasher->hashPassword($admin, 'admin'));
        $manager->persist($admin);

        // Couple random users
        for ($i = 0; $i < 10; ++$i) {
            $user = new User();
            $user->setName($faker->name());
            $user->setEmail($faker->unique()->safeEmail());
            $user->setRoles([Roles::ROLE_USER]);
            $user->setPassword($this->passwordHasher->hashPassword($user, 'pass'));

            $manager->persist($user);
        }

        $manager->flush();
    }
}
