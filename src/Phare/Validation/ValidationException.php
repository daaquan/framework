<?php

namespace Phare\Validation;

use Phare\Contracts\Http\Validation\Validator;

class ValidationException extends \Exception
{
    protected Validator $validator;

    protected int $status = 422;

    public function __construct(Validator $validator, string $message = 'The given data was invalid.', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);

        $this->validator = $validator;
    }

    public function getValidator(): Validator
    {
        return $this->validator;
    }

    public function errors(): MessageBag
    {
        return $this->validator->errors();
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function setStatus(int $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function errorBag(): string
    {
        return 'default';
    }

    public static function withMessages(array $messages): self
    {
        $validator = new class($messages) implements Validator
        {
            private MessageBag $errors;

            public function __construct(array $messages)
            {
                $this->errors = new MessageBag($messages);
            }

            public function passes(): bool
            {
                return false;
            }

            public function fails(): bool
            {
                return true;
            }

            public function errors(): MessageBag
            {
                return $this->errors;
            }

            public function validated(): array
            {
                return [];
            }

            public function safe(): array
            {
                return [];
            }
        };

        return new static($validator);
    }
}
