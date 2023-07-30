<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;
use OpenApi\Annotations as OA;

/**
 * @ORM\Entity
 *
 * @OA\Schema(
 *     description="Employee",
 *     type="object",
 *     required={"id", "firstName", "lastName", "email", "birthdate", "gender", "pesel"},
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="firstName", type="string"),
 *     @OA\Property(property="lastName", type="string"),
 *     @OA\Property(property="email", type="string"),
 *     @OA\Property(property="birthdate", type="string", format="date"),
 *     @OA\Property(property="gender", type="object", ref="#/components/schemas/Gender"),
 *     @OA\Property(property="pesel", type="string")
 * )
 */
class Employee
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=100)
     * @Assert\NotBlank
     */
    private $firstName;

    /**
     * @ORM\Column(type="string", length=100)
     * @Assert\NotBlank
     */
    private $lastName;

    /**
     * @ORM\Column(type="string", length=255, unique=true)
     * @Assert\NotBlank
     * @Assert\Email
     */
    private $email;

    /**
     * @ORM\Column(type="string", length=255)
     * @Assert\NotBlank
     * @Assert\Length(min=8, minMessage="Hasło musi mieć co najmniej 8 znaków.")
     */
    private $password;

    /**
     * @ORM\Column(type="date")
     * @Assert\NotBlank
     * @Assert\LessThanOrEqual("today", message="Data urodzenia nie może być z przyszłości.")
     */
    private $birthdate;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Gender", inversedBy="employees")
     * @ORM\JoinColumn(nullable=false)
     * @Assert\NotBlank
     */
    private $gender;

    /**
     * @ORM\Column(type="string", length=11, unique=true)
     * @Assert\NotBlank
     * @Assert\Regex(pattern="/^\d{11}$/", message="Numer PESEL musi składać się z 11 cyfr.")
     * @Assert\Callback(callback="validatePesel")
     */
    private $pesel;

    // Getters and setters...

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): self
    {
        $this->firstName = $firstName;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getLastName()
    {
        return $this->lastName;
    }

    /**
     * @param mixed $lastName
     */
    public function setLastName($lastName): void
    {
        $this->lastName = $lastName;
    }

    /**
     * @return mixed
     */
    public function getEmail()
    {
        return $this->email;
    }

    /**
     * @param mixed $email
     */
    public function setEmail($email): void
    {
        $this->email = $email;
    }

    /**
     * @return mixed
     */
    public function getPassword()
    {
        return $this->password;
    }

    /**
     * @param mixed $password
     */
    public function setPassword($password): void
    {
        $this->password = $password;
    }

    /**
     * @return mixed
     */
    public function getBirthdate()
    {
        return $this->birthdate;
    }

    /**
     * @param mixed $birthdate
     */
    public function setBirthdate($birthdate): void
    {
        $this->birthdate = $birthdate;
    }

    public function getGender(): ?Gender
    {
        return $this->gender;
    }

    public function setGender(?Gender $gender): self
    {
        $this->gender = $gender;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getPesel()
    {
        return $this->pesel;
    }

    /**
     * @param mixed $pesel
     */
    public function setPesel($pesel): void
    {
        $this->pesel = $pesel;
    }
}
