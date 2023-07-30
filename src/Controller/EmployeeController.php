<?php

namespace App\Controller;

use App\Entity\Employee;
use App\Entity\Gender;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Nelmio\ApiDocBundle\Annotation\Model;
use Nelmio\ApiDocBundle\Annotation\Security;
use OpenApi\Annotations as OA;

/**
 * @Route("/api")
 */
class EmployeeController extends AbstractController
{
    /**
     * @Route("/login", name="app_login", methods={"POST"})
     *
     * @OA\Post(
     *     path="/api/login",
     *     summary="Log in user",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="email", type="string"),
     *             @OA\Property(property="password", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="JWT token",
     *         @OA\JsonContent(
     *             @OA\Property(property="token", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Invalid credentials"
     *     )
     * )
     */
    public function login(JWTTokenManagerInterface $JWTManager): JsonResponse
    {
        $user = $this->getUser();
        $token = $JWTManager->create($user);

        return $this->json(['token' => $token]);
    }

    /**
     * @Route("/employees", name="app_create_employee", methods={"POST"})
     *
     * @OA\Post(
     *     path="/api/employees",
     *     summary="Create a new employee",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref=@Model(type=Employee::class, groups={"create"}))
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Employee created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Validation errors"
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     * @throws Exception
     */
    public function createEmployee(
        Request $request,
        UserPasswordEncoderInterface $passwordEncoder,
        ValidatorInterface $validator,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $data = json_decode($request->getContent(), true);

        $errors = $this->validateEmployeeData($data, 'create');
        if (count($errors) > 0) {
            return $this->json($errors, Response::HTTP_BAD_REQUEST);
        }

        $employee = new Employee();
        $employee->setFirstName($data['firstName']);
        $employee->setLastName($data['lastName']);
        $employee->setEmail($data['email']);
        $employee->setBirthdate(new DateTime($data['birthdate']));
        $employee->setPesel($data['pesel']);

        $gender = $entityManager->getRepository(Gender::class)->findOneBy(['name' => $data['gender']]);
        if (!$gender) {
            $gender = new Gender();
            $gender->setName($data['gender']);
            $entityManager->persist($gender);
        }
        $employee->setGender($gender);

        if (!$this->isPeselValid($data['pesel'])) {
            return $this->json(['message' => 'Invalid PESEL'], Response::HTTP_BAD_REQUEST);
        }

        $password = $data['password'];
        $passwordConfirmation = $data['passwordConfirmation'];
        if (!$this->isPasswordValid($password, $passwordConfirmation)) {
            return $this->json(['message' => 'Invalid password or password confirmation'], Response::HTTP_BAD_REQUEST);
        }

        $encodedPassword = $passwordEncoder->encodePassword($employee, $password);
        $employee->setPassword($encodedPassword);

        $validationErrors = $validator->validate($employee, null, ['create']);
        if (count($validationErrors) > 0) {
            $errors = [];
            foreach ($validationErrors as $error) {
                $errors[$error->getPropertyPath()] = $error->getMessage();
            }
            return $this->json($errors, Response::HTTP_BAD_REQUEST);
        }

        $entityManager->persist($employee);
        $entityManager->flush();

        return $this->json(['message' => 'Employee created successfully'], Response::HTTP_CREATED);
    }

    /**
     * @Route("/employees/{id}", name="app_edit_employee", methods={"PUT"})
     *
     * @OA\Put(
     *     path="/api/employees/{id}",
     *     summary="Edit an existing employee",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the employee",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref=@Model(type=Employee::class, groups={"edit"}))
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Employee updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Validation errors"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Employee not found"
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     * @throws Exception
     */
    public function editEmployee(
        Request $request,
        int $id,
        UserPasswordEncoderInterface $passwordEncoder,
        ValidatorInterface $validator,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $data = json_decode($request->getContent(), true);

        $employee = $entityManager->getRepository(Employee::class)->find($id);
        if (!$employee) {
            return $this->json(['message' => 'Employee not found'], Response::HTTP_NOT_FOUND);
        }

        $errors = $this->validateEmployeeData($data, 'edit');
        if (count($errors) > 0) {
            return $this->json($errors, Response::HTTP_BAD_REQUEST);
        }

        $employee->setFirstName($data['firstName']);
        $employee->setLastName($data['lastName']);
        $employee->setEmail($data['email']);
        $employee->setBirthdate(new DateTime($data['birthdate']));
        $employee->setPesel($data['pesel']);

        $gender = $entityManager->getRepository(Gender::class)->findOneBy(['name' => $data['gender']]);
        if (!$gender) {
            $gender = new Gender();
            $gender->setName($data['gender']);
            $entityManager->persist($gender);
        }
        $employee->setGender($gender);

        if (isset($data['password'])) {
            $password = $data['password'];
            if (!$this->isPasswordValid($password)) {
                return $this->json(['message' => 'Password is too simple'], Response::HTTP_BAD_REQUEST);
            }

            $encodedPassword = $passwordEncoder->encodePassword($employee, $password);
            $employee->setPassword($encodedPassword);
        }

        if (!$this->isPeselValid($data['pesel'])) {
            return $this->json(['message' => 'Invalid PESEL'], Response::HTTP_BAD_REQUEST);
        }

        $validationErrors = $validator->validate($employee, null, ['edit']);
        if (count($validationErrors) > 0) {
            $errors = [];
            foreach ($validationErrors as $error) {
                $errors[$error->getPropertyPath()] = $error->getMessage();
            }
            return $this->json($errors, Response::HTTP_BAD_REQUEST);
        }

        $entityManager->flush();

        return $this->json(['message' => 'Employee updated successfully'], Response::HTTP_OK);
    }

    /**
     * @Route("/employees/{id}", name="app_get_employee", methods={"GET"})
     *
     * @OA\Get(
     *     path="/api/employees/{id}",
     *     summary="Get details of an employee",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the employee",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Employee details",
     *         @OA\JsonContent(ref=@Model(type=Employee::class))
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Employee not found"
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function getEmployee(int $id, EntityManagerInterface $entityManager): JsonResponse
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $employee = $entityManager->getRepository(Employee::class)->find($id);
        if (!$employee) {
            return $this->json(['message' => 'Employee not found'], Response::HTTP_NOT_FOUND);
        }

        $employeeData = [
            'id' => $employee->getId(),
            'firstName' => $employee->getFirstName(),
            'lastName' => $employee->getLastName(),
            'email' => $employee->getEmail(),
            'birthdate' => $employee->getBirthdate()->format('Y-m-d'),
            'gender' => $employee->getGender()->getName(),
            'pesel' => $employee->getPesel(),
        ];

        return $this->json($employeeData, Response::HTTP_OK);
    }

    /**
     * @Route("/employees", name="app_list_employees", methods={"GET"})
     *
     * @OA\Get(
     *     path="/api/employees",
     *     summary="Get a list of employees",
     *     @OA\Parameter(
     *         name="firstName",
     *         in="query",
     *         required=false,
     *         description="Filter by first name",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="lastName",
     *         in="query",
     *         required=false,
     *         description="Filter by last name",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="email",
     *         in="query",
     *         required=false,
     *         description="Filter by email",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="gender",
     *         in="query",
     *         required=false,
     *         description="Filter by gender",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="orderBy",
     *         in="query",
     *         required=false,
     *         description="Sort by fields (e.g., 'firstName,-lastName' for ascending firstName and descending lastName)",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="pageSize",
     *         in="query",
     *         required=false,
     *         description="Number of items per page",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="currentPage",
     *         in="query",
     *         required=false,
     *         description="Current page number",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of employees",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(ref=@Model(type=Employee::class))
     *         )
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function listEmployees(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $criteria = [];
        $orderBy = [];
        $pageSize = 10;
        $currentPage = 1;

        $filters = $request->query->all();
        if (!empty($filters['firstName'])) {
            $criteria['firstName'] = $filters['firstName'];
        }
        if (!empty($filters['lastName'])) {
            $criteria['lastName'] = $filters['lastName'];
        }
        if (!empty($filters['email'])) {
            $criteria['email'] = $filters['email'];
        }
        if (!empty($filters['gender'])) {
            $criteria['gender'] = $filters['gender'];
        }

        if (!empty($filters['orderBy'])) {
            $orderBy = $this->parseOrderBy($filters['orderBy']);
        }

        if (!empty($filters['pageSize']) && is_numeric($filters['pageSize'])) {
            $pageSize = max(1, (int) $filters['pageSize']);
        }
        if (!empty($filters['currentPage']) && is_numeric($filters['currentPage'])) {
            $currentPage = max(1, (int) $filters['currentPage']);
        }

        $employeeRepository = $entityManager->getRepository(Employee::class);
        $paginator = $employeeRepository->searchEmployees($criteria, $orderBy, $pageSize, $currentPage);

        $employeeData = [];
        foreach ($paginator as $employee) {
            $employeeData[] = [
                'id' => $employee->getId(),
                'firstName' => $employee->getFirstName(),
                'lastName' => $employee->getLastName(),
                'email' => $employee->getEmail(),
                'birthdate' => $employee->getBirthdate()->format('Y-m-d'),
                'gender' => $employee->getGender()->getName(),
                'pesel' => $employee->getPesel(),
            ];
        }

        return $this->json([
            'data' => $employeeData,
            'currentPage' => $paginator->getCurrentPage(),
            'totalItems' => $paginator->getTotalItemCount(),
            'itemsPerPage' => $paginator->getItemNumberPerPage(),
        ], Response::HTTP_OK);
    }

    /**
     * Helper method to parse the 'orderBy' query parameter and return the sorting criteria.
     *
     * @param string $orderByParam
     * @return array
     */
    private function parseOrderBy(string $orderByParam): array
    {
        $orderBy = [];
        $fields = explode(',', $orderByParam);

        foreach ($fields as $field) {
            $field = trim($field);
            if (str_starts_with($field, '-')) {
                $orderBy[substr($field, 1)] = 'DESC';
            } else {
                $orderBy[$field] = 'ASC';
            }
        }

        return $orderBy;
    }

    /**
     * Helper method to validate the employee data received in the request.
     *
     * @param array $data
     * @param string $validationGroup The validation group to use ("create" or "edit").
     * @return array An array of validation errors, if any.
     */
    private function validateEmployeeData(array $data, string $validationGroup): array
    {
        $errors = [];

        if (empty($data['firstName'])) {
            $errors['firstName'] = 'First name is required.';
        }

        if (empty($data['lastName'])) {
            $errors['lastName'] = 'Last name is required.';
        }

        if (empty($data['email'])) {
            $errors['email'] = 'Email is required.';
        }

        if (empty($data['birthdate'])) {
            $errors['birthdate'] = 'Birthdate is required.';
        } elseif (!DateTime::createFromFormat('Y-m-d', $data['birthdate'])) {
            $errors['birthdate'] = 'Invalid birthdate format. Expected format: Y-m-d.';
        }

        if (empty($data['gender'])) {
            $errors['gender'] = 'Gender is required.';
        }

        if ($validationGroup === 'create') {
            if (empty($data['password'])) {
                $errors['password'] = 'Password is required.';
            }
            if (empty($data['passwordConfirmation'])) {
                $errors['passwordConfirmation'] = 'Password confirmation is required.';
            } elseif ($data['password'] !== $data['passwordConfirmation']) {
                $errors['passwordConfirmation'] = 'Password confirmation does not match.';
            }
        }

        return $errors;
    }

    /**
     * Helper method to check if the given password is valid and meets complexity requirements.
     *
     * @param string $password
     * @param EntityManagerInterface $entityManager
     * @return bool
     */
    private function isPasswordValid(string $password, EntityManagerInterface $entityManager): bool
    {
        if (strlen($password) < 8) {
            return false;
        }
        $existingEmployee = $entityManager->getRepository(Employee::class)->findOneBy(['password' => $password]);
        return !$existingEmployee;
    }



    /**
     * Helper method to check if the given PESEL has a valid control digit.
     *
     * @param string $pesel
     * @return bool
     */
    private function isPeselValid(string $pesel): bool
    {

        $pattern = '/^[0-9]{11}$/';
        if (!preg_match($pattern, $pesel)) {
            return false;
        }

        $weights = [1, 3, 7, 9, 1, 3, 7, 9, 1, 3];
        $sum = 0;

        for ($i = 0; $i < 10; $i++) {
            $sum += $pesel[$i] * $weights[$i];
        }

        $controlDigit = 10 - $sum % 10;
        if ($controlDigit === 10) {
            $controlDigit = 0;
        }

        return $controlDigit === (int) $pesel[10];
    }
}
