<?php
declare(strict_types=1);

class Validation
{
    private array $rules = [];
    private array $labels = [];
    private array $errors = [];
    private ?array $data = null;

    private array $messages = [
        'required'     => '{field} is required.',
        'min_length'   => '{field} must be at least {param} characters long.',
        'max_length'   => '{field} cannot exceed {param} characters.',
        'exact_length' => '{field} must be exactly {param} characters long.',
        'valid_email'  => '{field} must be a valid email address.',
        'valid_url'    => '{field} must be a valid URL.',
        'valid_ip'     => '{field} must be a valid IP address.',
        'numeric'      => '{field} must be numeric.',
        'integer'      => '{field} must be an integer.',
        'is_natural'   => '{field} must contain only digits.',
        'alpha'        => '{field} may only contain letters.',
        'alpha_numeric' => '{field} may only contain letters and numbers.',
        'matches'      => '{field} does not match the {param} field.',
        'regex_match'  => '{field} is not in the correct format.',
        'in_list'      => '{field} must be one of: {param}.',
        'greater_than' => '{field} must be greater than {param}.',
        'less_than'    => '{field} must be less than {param}.',
    ];

    public function set_data(array $data): static
    {
        $this->data = $data;
        return $this;
    }

    public function set_rules(string $field, string $label, string|array $rules): static
    {
        $this->labels[$field] = $label;
        $this->rules[$field]  = is_string($rules) ? explode('|', $rules) : $rules;
        return $this;
    }

    public function set_message(string $rule, string $message): static
    {
        $this->messages[$rule] = $message;
        return $this;
    }

    public function run(?array $data = null): bool
    {
        $data ??= $this->data ?? (array) Framework::core('input')->post();
        $this->errors = [];

        foreach ($this->rules as $field => $rules) {
            $value    = $data[$field] ?? null;
            $required = in_array('required', $rules, true);
            $empty    = $value === null || $value === '' || $value === [];

            if ($empty && !$required) {
                continue;
            }

            foreach ($rules as $rule) {
                $error = $this->apply($rule, $value, $data, $field);
                if ($error !== null) {
                    $this->errors[$field] = $error;
                    break;
                }
            }
        }

        return $this->errors === [];
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function error(string $field): ?string
    {
        return $this->errors[$field] ?? null;
    }

    private function apply(mixed $rule, mixed $value, array $data, string $field): ?string
    {
        $label = $this->labels[$field] ?? $field;

        if (is_callable($rule)) {
            $result = $rule($value, $data);
            return $result === true ? null : str_replace('{field}', $label, is_string($result) ? $result : '{field} is invalid.');
        }

        if (!preg_match('/^([a-z_]+)(?:\[(.*)\])?$/s', (string) $rule, $m)) {
            throw new InvalidArgumentException("Invalid validation rule: {$rule}");
        }
        $name  = $m[1];
        $param = $m[2] ?? null;

        if ($this->check($name, $value, $param, $data)) {
            return null;
        }

        return str_replace(['{field}', '{param}'], [$label, (string) $param], $this->messages[$name] ?? '{field} is invalid.');
    }

    private function check(string $rule, mixed $v, ?string $param, array $data): bool
    {
        if (is_array($v)) {
            return $rule === 'required' ? $v !== [] : false;
        }

        $s = (string) $v;

        return match ($rule) {
            'required'      => trim($s) !== '',
            'min_length'    => mb_strlen($s) >= (int) $param,
            'max_length'    => mb_strlen($s) <= (int) $param,
            'exact_length'  => mb_strlen($s) === (int) $param,
            'valid_email'   => filter_var($s, FILTER_VALIDATE_EMAIL) !== false,
            'valid_url'     => filter_var($s, FILTER_VALIDATE_URL) !== false,
            'valid_ip'      => filter_var($s, FILTER_VALIDATE_IP) !== false,
            'numeric'       => is_numeric($s),
            'integer'       => filter_var($s, FILTER_VALIDATE_INT) !== false,
            'is_natural'    => ctype_digit($s),
            'alpha'         => preg_match('/^\p{L}+$/u', $s) === 1,
            'alpha_numeric' => preg_match('/^[\p{L}\p{N}]+$/u', $s) === 1,
            'matches'       => $s === (string) ($data[(string) $param] ?? ''),
            'regex_match'   => preg_match((string) $param, $s) === 1,
            'in_list'       => in_array($s, explode(',', (string) $param), true),
            'greater_than'  => is_numeric($s) && (float) $s > (float) $param,
            'less_than'     => is_numeric($s) && (float) $s < (float) $param,
            default         => throw new InvalidArgumentException("Unknown validation rule: {$rule}"),
        };
    }
}
