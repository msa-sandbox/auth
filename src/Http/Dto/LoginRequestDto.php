<?php

declare(strict_types=1);

namespace App\Http\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class LoginRequestDto
{
    public function __construct(
        #[Assert\NotBlank(message: 'Missing email')]
        #[Assert\Email(message: 'Invalid email format')]
        private mixed $email,
        #[Assert\NotBlank(message: 'Missing password')]
        private mixed $password,
    ) {
    }

    public function getEmail(): string
    {
        return (string) $this->email;
    }

    public function getPassword(): string
    {
        return (string) $this->password;
    }
}
