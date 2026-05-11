<?php

declare(strict_types=1);

namespace App\Authentication\Controller;

use App\Authentication\Controller\Input\RegisterInput;
use App\Authentication\Enum\FailedResendReason;
use App\Authentication\Service\RegistrationService;
use LogicException;
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

    #[Route('/login', name: 'auth_login', methods: ['POST'])]
    public function login(): JsonResponse
    {
        throw new LogicException('Not implemented');
    }
}
