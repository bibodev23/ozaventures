<?php

namespace App\DataFixtures;

use App\Entity\Kid;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Faker;

class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $faker = Faker\Factory::create('fr_FR') ;
        
        for ($i = 0; $i < 80; $i++) {
            $kid = new Kid() ;
            $kid->setFirstname($faker->firstName()) ;
            $kid->setLastname($faker->lastName()) ;
            $kid->setAge($faker->numberBetween(3, 12)) ;
            $manager->persist($kid);
        }
        $manager->flush();
    }
}
