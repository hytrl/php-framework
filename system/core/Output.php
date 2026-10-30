<?php
declare(strict_types=1);

final class Output
{
    private int $status = 200;
    /** @var array<string, string> küçük harfli ad => "Ad: değer" */
    private array $headers = [];
    private ?string $override = null;
    private string $charset;

    public function __construct(Config $config)
    {
        $this->charset = (string) $config->item('charset', 'UTF-8');
        $this->set_content_type('text/html');
    }

    public function set_status_header(int $code): static
    {
        $this->status = $code;
        return $this;
    }

    public function set_header(string $name, string $value): static
    {
        $name  = str_replace(["\r", "\n", ':'], '', $name);
        $value = str_replace(["\r", "\n"], '', $value);
        $this->headers[strtolower($name)] = $name . ': ' . $value;
        return $this;
    }

    public function set_content_type(string $mime): static
    {
        $charset = (str_starts_with($mime, 'text/') || str_contains($mime, 'json') || str_contains($mime, 'xml'))
            ? '; charset=' . $this->charset : '';
        return $this->set_header('Content-Type', $mime . $charset);
    }

    public function set_output(string $body): static
    {
        $this->override = $body;
        return $this;
    }

    public function json(mixed $data, int $status = 200, int $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES): static
    {
        $this->status = $status;
        $this->set_content_type('application/json');
        $this->override = json_encode($data, $flags | JSON_THROW_ON_ERROR);
        return $this;
    }

    public function send(string $buffered): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);
            foreach ($this->headers as $line) {
                header($line, true);
            }
        }
        echo $this->override ?? $buffered;
    }
}
