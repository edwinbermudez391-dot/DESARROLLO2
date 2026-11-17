<?php
declare(strict_types=1);

require_once __DIR__ . '/HttpException.php';

abstract class Controller
{
    protected function input(): array
    {
        if (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') === false) {
            return $_POST;
        }
        try {
            $raw = file_get_contents('php://input');
            $decoded = json_decode($raw, false, 512, JSON_THROW_ON_ERROR);
            if (!is_object($decoded)) {
                throw new HttpException('El cuerpo JSON debe ser un objeto.', 400);
            }
            return json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new HttpException('El cuerpo de la petición contiene JSON inválido.', 400);
        }
    }

    protected function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
