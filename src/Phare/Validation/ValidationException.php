<?php

namespace Phare\Validation;

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
        $data = [];
        $rules = [];

        foreach (array_keys($messages) as $key) {
            $rules[$key] = 'required';
        }

        $validator = new Validator($data, $rules);
        $validator->passes();

        $bag = new MessageBag($messages);

        return new static(new class($bag) extends Validator
        {
            private MessageBag $errorBag;

            public function __construct(MessageBag $bag)
            {
                parent::__construct([], []);
                $this->errorBag = $bag;
            }

            public function errors(): MessageBag
            {
                return $this->errorBag;
            }

            public function passes(): bool
            {
                return false;
            }

            public function validated(): array
            {
                return [];
            }

            public function safe(): array
            {
                return [];
            }
        });
    }
}
