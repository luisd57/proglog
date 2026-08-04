<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller;

use Symfony\Component\Validator\ConstraintViolationListInterface;

trait ValidatesRequestTrait
{
    /**
     * Convert Symfony Validator violations into a flat field → message array
     * compatible with the validationError() response format.
     *
     * @return array<string, string>
     */
    protected function violationsToErrors(ConstraintViolationListInterface $violations): array
    {
        $errors = [];

        foreach ($violations as $violation) {
            $field = $violation->getPropertyPath();

            if ($field === '' || $field === null) {
                $field = 'general';
            }

            // Strip array notation from property paths (e.g., "[email]" → "email")
            $field = trim($field, '[]');

            // Only keep the first error per field
            if (!isset($errors[$field])) {
                $errors[$field] = (string) $violation->getMessage();
            }
        }

        return $errors;
    }
}
