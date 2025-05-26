<?php

namespace App\Billingo\Validation;

use App\Billingo\Contracts\ValidableInterface;
use App\Billingo\Enums\BillingoErrorEnum;
use App\Billingo\Error\BillingoError;
use App\Billingo\Exceptions\EmptyObjectException;
use App\Billingo\Exceptions\ValidationException;
use InvalidArgumentException;
use Valitron\Validator as Valitron;

abstract class Validator implements ValidableInterface
{
    protected readonly array $rules;
    protected ?BillingoError $errors = null;

    public function __construct()
    {
        $this->rules = $this->rules();
    }

    abstract protected function rules(): array;

    /**
     * @throws EmptyObjectException
     * @throws ValidationException
     */
    public function validate(array $data): array
    {
        $relevantData = $this->filterInvalidItems($data, $this->rules);

        if (empty($relevantData)) {

            throw new EmptyObjectException();
        }

        $validator = $this->createValidator($relevantData, $this->rules);

        if ($validator->validate()) {

            return $relevantData;
        } else {

            throw new ValidationException($validator->errors());
        }
    }

    public function validateProperty(string $key, mixed $value): bool
    {
        if (!isset($this->rules[$key])) {

            return false;
        }

        $validator = $this->createValidator(
            [$key => $value],
            [$key => $this->rules[$key]]);

        return $validator->validate();
    }

    public function createValidator(array $relevantData, array $rules): Valitron
    {
        $validator = new Valitron($relevantData);

        foreach ($rules as $field => $fieldRules) {
            foreach ($fieldRules as $rule) {
                try {
                    if (is_array($rule)) {
                        $validator->rule($rule[0], $field, $rule[1]);
                    } else {
                        $validator->rule($rule, $field);
                    }
                } catch (InvalidArgumentException $e) {

                    //TODO: log?

                    continue;
                }
            }
        }

        return $validator;
    }

    public function filterInvalidItems(array $data, array $rules): array
    {
        $relevantData = array_intersect_key($data, $rules);

        return array_filter($relevantData, function ($value, $key) use ($rules) {

            return !is_null($value) || in_array('nullable', $rules[$key]);
        }, ARRAY_FILTER_USE_BOTH);

    }

    public function getRules(): array
    {
        return $this->rules;
    }

    public function getErrors(): ?BillingoError
    {
        return $this->errors;
    }

    public function setErrors(BillingoErrorEnum $type, array $errors = null): void
    {
        if (is_null($this->getErrors())) {
            $this->errors = new BillingoError($type, $errors);

            return;
        }

        $this->errors->setType($type);
        $this->errors->setValues($errors);
    }
}
