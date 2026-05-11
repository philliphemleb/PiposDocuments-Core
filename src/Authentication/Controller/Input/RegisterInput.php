<?php

declare(strict_types=1);

namespace App\Authentication\Controller\Input;

use Symfony\Component\Validator\Constraints as Assert;

readonly class RegisterInput
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Email(mode: Assert\Email::VALIDATION_MODE_HTML5)]
        public string $email = '',
    ) {
    }
}
