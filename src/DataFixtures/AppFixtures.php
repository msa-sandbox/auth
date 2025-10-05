<?php

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use PHPUnit\Framework\Attributes\Group;

#[Group('base')]
class AppFixtures extends Fixture
{
    public function __construct(
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        // Should start all other fixtures
    }
}
