<?php

declare(strict_types=1);

namespace App\Validation;

use DateTimeImmutable;

final class AssignmentValidator
{
    public const STATUSES = ['ASSEGNATO', 'COMPLETATO', 'SCADUTO', 'ANNULLATO'];

    public function payload(array $data): array
    {
        $values = [
            'corso_id' => filter_var($data['corso_id'] ?? null, FILTER_VALIDATE_INT),
            'dipendente_id' => filter_var($data['dipendente_id'] ?? null, FILTER_VALIDATE_INT),
            'data_assegnazione' => trim((string) ($data['data_assegnazione'] ?? '')),
            'data_scadenza' => trim((string) ($data['data_scadenza'] ?? '')),
            'stato' => strtoupper(trim((string) ($data['stato'] ?? 'ASSEGNATO'))),
            'data_completamento' => $this->nullableString($data['data_completamento'] ?? null),
        ];
        $errors = [];

        if ($values['corso_id'] === false || $values['corso_id'] <= 0) {
            $errors['corso_id'] = 'L’assegnazione deve essere associata a un corso';
        }

        if ($values['dipendente_id'] === false || $values['dipendente_id'] <= 0) {
            $errors['dipendente_id'] = 'L’assegnazione deve essere associata a un dipendente';
        }

        if (!$this->isDate($values['data_assegnazione'])) {
            $errors['data_assegnazione'] = 'La data di assegnazione deve avere formato YYYY-MM-DD';
        }

        if (!$this->isDate($values['data_scadenza'])) {
            $errors['data_scadenza'] = 'La data di scadenza deve avere formato YYYY-MM-DD';
        } elseif ($this->isDate($values['data_assegnazione'])
            && $values['data_scadenza'] < $values['data_assegnazione']
        ) {
            $errors['data_scadenza'] = 'La scadenza non può precedere la data di assegnazione';
        }

        if (!in_array($values['stato'], self::STATUSES, true)) {
            $errors['stato'] = 'Lo stato non appartiene all’insieme previsto';
        }

        $completion = $values['data_completamento'];
        if ($completion !== null && !$this->isDate($completion)) {
            $errors['data_completamento'] = 'La data di completamento deve avere formato YYYY-MM-DD';
        } elseif ($completion !== null
            && $this->isDate($values['data_assegnazione'])
            && $completion < $values['data_assegnazione']
        ) {
            $errors['data_completamento'] = 'Il completamento non può precedere l’assegnazione';
        } elseif ($completion !== null
            && $completion > (new DateTimeImmutable('today'))->format('Y-m-d')
        ) {
            $errors['data_completamento'] = 'La data di completamento non può essere futura';
        }

        if ($values['stato'] === 'COMPLETATO' && $completion === null) {
            $errors['data_completamento'] = 'La data di completamento è obbligatoria per lo stato COMPLETATO';
        } elseif ($values['stato'] !== 'COMPLETATO' && $completion !== null) {
            $errors['data_completamento'] = 'La data di completamento è ammessa solo per lo stato COMPLETATO';
        }

        return [$values, $errors];
    }

    public function filters(array $query, bool $canFilterByEmployee): array
    {
        $filters = [];
        $errors = [];

        if ($canFilterByEmployee && ($query['dipendente_id'] ?? '') !== '') {
            $filters['dipendente_id'] = filter_var($query['dipendente_id'], FILTER_VALIDATE_INT);
            if ($filters['dipendente_id'] === false || $filters['dipendente_id'] <= 0) {
                $errors['dipendente_id'] = 'dipendente_id non valido';
            }
        }

        if (trim((string) ($query['stato'] ?? '')) !== '') {
            $filters['stato'] = strtoupper(trim((string) $query['stato']));
            if (!in_array($filters['stato'], self::STATUSES, true)) {
                $errors['stato'] = 'Stato non valido';
            }
        }

        if (trim((string) ($query['categoria'] ?? '')) !== '') {
            $filters['categoria'] = trim((string) $query['categoria']);
        }

        if (($query['corso_id'] ?? '') !== '') {
            $filters['corso_id'] = filter_var($query['corso_id'], FILTER_VALIDATE_INT);
            if ($filters['corso_id'] === false || $filters['corso_id'] <= 0) {
                $errors['corso_id'] = 'corso_id non valido';
            }
        }

        foreach (['scadenza_da', 'scadenza_a'] as $field) {
            if (($query[$field] ?? '') !== '') {
                $filters[$field] = trim((string) $query[$field]);
                if (!$this->isDate($filters[$field])) {
                    $errors[$field] = "{$field} deve avere formato YYYY-MM-DD";
                }
            }
        }

        return [$filters, $errors];
    }

    private function nullableString(mixed $value): ?string
    {
        return $value === null || $value === '' ? null : trim((string) $value);
    }

    private function isDate(string $value): bool
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value;
    }
}
