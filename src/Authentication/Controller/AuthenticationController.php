<?php

declare(strict_types=1);

namespace App\Authentication\Controller;

use App\Authentication\Controller\Input\RegisterInput;
use App\Authentication\Enum\FailedResendReason;
use App\Authentication\Exception\FailedRegistrationException;
use App\Authentication\Exception\FailedResendException;
use App\Authentication\Service\RegistrationService;
use App\Authentication\Service\ResendVerificationEmailService;
use LogicException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

class AuthenticationController extends AbstractController
{
    #[Route('/register', name: 'auth_register', methods: ['POST'])]
    public function register(
        #[MapRequestPayload]
        RegisterInput $input,
        RegistrationService $registrationService,
    ): JsonResponse {
        try {
            $registrationService->register($input->email);
        } catch (FailedRegistrationException) {
            return $this->json(['error' => 'Email not available.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->json(null, Response::HTTP_CREATED);
    }

    #[Route('/resend-verification-email', name: 'auth_resend_verification_email', methods: ['POST'])]
    public function resendVerificationEmail(
        #[MapRequestPayload]
        RegisterInput $input,
        ResendVerificationEmailService $resendService,
    ): JsonResponse {
        try {
            $resendService->resend($input->email);
        } catch (FailedResendException $e) {
            $status = FailedResendReason::MaxAttemptsReached === $e->reason
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
