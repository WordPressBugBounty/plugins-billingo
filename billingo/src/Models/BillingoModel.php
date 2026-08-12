<?php

namespace App\Billingo\Models;

use App\Billingo\Contracts\SelfControlInterface;
use App\Billingo\Contracts\SerializableInterface;
use App\Billingo\Contracts\ValidableInterface;
use App\Billingo\Enums\BillingoErrorEnum;
use App\Billingo\Error\BillingoError;
use App\Billingo\Exceptions\EmptyObjectException;
use App\Billingo\Exceptions\ValidationException;
use App\Billingo\Traits\WithSelfControl;

abstract class BillingoModel implements SerializableInterface, SelfControlInterface
{
    use WithSelfControl;

    protected ValidableInterface $validator;
    protected array $cast = [];
    protected array $properties;

    public function __construct(array $data = [], ?ValidableInterface $validator = null)
    {
        $this->validator = $validator ?? $this->getValidator();

        $this->fromArray($data);
    }

    abstract protected function getValidator(): ValidableInterface;

    public function toArray(): array
    {
        $toArray = $this->properties;

        foreach ($toArray as $key => $value) {
            $toArray[$key] = $this->serializeValue($value);
        }

        return $toArray;
    }

    public function fromArray(array $data): self
    {
        if (empty($data)) {
            $this->properties = [];

            return $this;
        }

        $withClasses = $this->createClasses($data, $this->cast);

        $validated = $withClasses;

        try {

            $validated = $this->validator->validate($withClasses);
        } catch (EmptyObjectException $exception) {
            $this->validator->setErrors(BillingoErrorEnum::EMPTY_OBJECT);
            $validated = [];
            // Ha ide jutunk, a bejövő adat egyetlen kulcsa sem egyezett a validátor által
            // ismert mezőnevekkel — ilyenkor a modell teljesen üresen marad, mert nincs mit
            // megőrizni. Naplózzuk a nyers bemenet kulcsait és a modell osztályát, mert ez
            // az egyetlen pont, ahol ez az információ még elérhető — a hívási lánc további
            // részében ez már elveszik.
            error_log(sprintf(
                'BillingoModel::fromArray() EMPTY_OBJECT on %s — received keys: [%s], expected keys: [%s]',
                static::class,
                implode(', ', array_keys($data)),
                implode(', ', array_keys($this->validator->getRules()))
            ));
        } catch (ValidationException $exception) {
            $this->validator->setErrors(BillingoErrorEnum::VALIDATION_FAILED, $exception->getErrors());
            // Validációs hiba esetén korábban a teljes $this->properties kiürült ($validated
            // maradt [] ), még akkor is, ha csak EGY mező nem felelt meg a validációnak.
            // Ez éles API-válaszok (pl. egy sikeresen létrehozott Billingo számla adatai)
            // feldolgozásakor azt eredményezte, hogy a $document->id / ->invoice_number /
            // ->type property-k mind null-t adtak vissza — így a helyi DB-mentés
            // (Billingo_Repositroy::getDatafromDocument()) csendben, hibaüzenet nélkül
            // kimaradt, annak ellenére, hogy a bizonylat ténylegesen létrejött a Billingo
            // oldalán. A hasError()/getErrors() továbbra is helyesen jelzi a hibát, de a
            // property-k mostantól megmaradnak, hogy a ténylegesen érvényes adatok (pl. az
            // ID) ne vesszenek el emiatt.
            $validated = $withClasses;
        }

        $this->properties = $validated;

        return $this;
    }

    private function serializeValue($value): mixed
    {
        if (is_object($value)) {
            return $value instanceof SerializableInterface ? $value->toArray() : $value;
        } elseif (is_array($value)) {
            return array_map(
                fn($item) => $item instanceof SerializableInterface ? $item->toArray() : $item,
                $value
            );
        }
        return $value;
    }

    protected function createClasses(array $data, array $castingList): array
    {
        $convertedData = [];

        foreach ($data as $key => $value) {
            $convertedData[$key] = $this->convertValue($key, $value, $castingList);
        }

        return $convertedData;
    }

    private function convertValue(string $key, mixed $value, array $castingList): mixed
    {
        if (is_object($value)) {

            return $value;
        } elseif (isset($castingList[$key])) {
            if (is_array($castingList[$key])) {

                return array_map(
                    fn($item) => is_object($item) ? $item : new $castingList[$key][0]($item),
                    $value
                );
            } else {

                return $value ? new $castingList[$key]($value) : null;
            }
        }

        return $value;
    }

    public function getErrors(): ?BillingoError
    {
        return $this->validator->getErrors();
    }

    public function __get(string $name): mixed
    {
        return $this->properties[$name] ?? null;
    }

    /**
     * __get() nélküli __isset() hiányában PHP az isset()/empty() hívásokat egy csak
     * __get()-en keresztül elérhető property-n MINDIG "nincs beállítva" eredménnyel zárná
     * le, a tényleges értéktől függetlenül — pl. az empty($document->id) mindig true lenne,
     * még akkor is, ha a document->id egy valós, nem nulla azonosító.
     */
    public function __isset(string $name): bool
    {
        return isset($this->properties[$name]);
    }


    public function __set(string $name, mixed $value): void
    {
        if (!$this->validator->validateProperty($name, $value)) {

            return;
        }

        if (isset($this->properties[$name])) {

            $this->properties[$name] = $value;
        }
    }
}
