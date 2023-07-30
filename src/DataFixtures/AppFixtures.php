<?php

namespace App\DataFixtures;

use App\Entity\Employee;
use App\Entity\Gender;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager)
    {
        $maleGender = new Gender();
        $maleGender->setName('Male');
        $maleGender->setCode('M');
        $manager->persist($maleGender);

        $femaleGender = new Gender();
        $femaleGender->setName('Female');
        $femaleGender->setCode('F');
        $manager->persist($femaleGender);

        for ($i = 1; $i <= 100; $i++) {
            $employee = new Employee();
            $employee->setFirstName('TestFirstName ' . $i);
            $employee->setLastName('TestLastName ' . $i);
            $employee->setEmail('test' . $i . '@example.com');
            $employee->setPassword('testpassword');
            $employee->setBirthdate(new \DateTime('1990-01-01'));

            $pesel = $this->generateUniquePesel();
            $employee->setPesel($pesel);

            $gender = $i % 2 === 0 ? $maleGender : $femaleGender;
            $employee->setGender($gender);

            $manager->persist($employee);
        }

        $manager->flush();
    }
    /**
     * Generate a unique PESEL number.
     *
     * @return string A unique PESEL number.
     *
     * @OA\Get(
     *     path="/api/fixtures/generate-pesel",
     *     summary="Generate a unique PESEL number",
     *     @OA\Response(
     *         response=200,
     *         description="A unique PESEL number is generated",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="pesel", type="string")
     *         )
     *     )
     * )
     */
    private function generateUniquePesel(): string
    {
        $pesel = '';
        for ($i = 0; $i < 11; $i++) {
            $pesel .= mt_rand(0, 9);
        }
        return $pesel;
    }
}
