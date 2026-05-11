<?php

declare(strict_types=1);

namespace App\Authentication\Controller;

use App\Authentication\Controller\Input\RegisterInput;
use App\Authentication\Enum\FailedResendReason;
use App\Authentication\Service\RegistrationService;
use LogicException;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

class AuthenticationController extends AbstractController
{
    public function __construct(
        private readonly RegistrationService $registrationService,
    ) {
    }

    #[OA\Post(
        path: '/api/register',
        summary: 'Register a new user',
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/RegisterInput')),
        responses: [
            new OA\Response(response: 201, description: 'User registered successfully'),
            new OA\Response(response: 422, description: 'Registration failed', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
        ],
    )]
    #[Route('/register', name: 'auth_register', methods: ['POST'])]
    public function register(
        #[MapRequestPayload]
        RegisterInput $input,
    ): JsonResponse {
        $result = $this->registrationService->register($input->email);

        if (!$result->success) {
            return $this->json(['error' => 'Email not available.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->json(null, Response::HTTP_CREATED);
    }

    #[OA\Post(
        path: '/api/resend-verification-email',
        summary: 'Resend the verification email',
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/RegisterInput')),
        responses: [
            new OA\Response(response: 202, description: 'Verification email queued'),
            new OA\Response(response: 422, description: 'Resend not possible', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 429, description: 'Max daily tokens reached', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
        ],
    )]
    #[Route('/resend-verification-email', name: 'auth_resend_verification_email', methods: ['POST'])]
    public function resendVerificationEmail(
        #[MapRequestPayload]
        RegisterInput $input,
    ): JsonResponse {
        $result = $this->registrationService->resendRegistrationVerification($input->email);

        if (!$result->success && $result->reason instanceof FailedResendReason) {
            $status = FailedResendReason::MaxAttemptsReached === $result->reason
                ? Response::HTTP_TOO_MANY_REQUESTS
                : Response::HTTP_UNPROCESSABLE_ENTITY;

            return $this->json(['error' => 'Resend not possible.'], $status);
        }

        return $this->json(null, Response::HTTP_ACCEPTED);
    }

    #[OA\Post(
        path: '/api/login',
        summary: 'Authenticate and get a JWT token',
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['email', 'password'], properties: [new OA\Property(property: 'email', type: 'string', format: 'email'), new OA\Property(property: 'password', type: 'string', format: 'password')])),
        responses: [
            new OA\Response(response: 200, description: 'Authentication successful', content: new OA\JsonContent(properties: [new OA\Property(property: 'token', type: 'string')])),
            new OA\Response(response: 401, description: 'Invalid credentials', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
        ],
    )]
    #[Route('/login', name: 'auth_login', methods: ['POST'])]
    public function login(): JsonResponse
    {
        throw new LogicException('Not implemented');
    }
}
