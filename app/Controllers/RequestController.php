<?php
declare(strict_types=1);

require_once __DIR__ . '/../../core/Controller.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../Models/RequestModel.php';

class RequestController extends Controller
{
    public function store(): void
    {
        $auth = new Auth();
        $auth->requireRole([ROL_VENTANILLA, ROL_ADMINISTRADOR]);
        $input = $this->input();
        foreach ($input as $value) {
            if ($value !== null && !is_scalar($value)) {
                throw new HttpException('Los campos de la solicitud deben ser valores simples.', 400);
            }
        }
        $result = (new RequestModel())->createSolicitud(
            $input, (int) $auth->getUserId(), $_SERVER['REMOTE_ADDR'] ?? null
        );
        $this->json($result, $result['success'] ? 200 : 400);
    }

    public function geolocation(): void
    {
        $auth = new Auth();
        $auth->requireRole([ROL_VENTANILLA, ROL_ADMINISTRADOR]);
        $input = $this->input();
        $id = filter_var($input['solicitud_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $lat = filter_var($input['latitud'] ?? null, FILTER_VALIDATE_FLOAT);
        $lng = filter_var($input['longitud'] ?? null, FILTER_VALIDATE_FLOAT);
        if ($id === false || $lat === false || $lng === false) {
            throw new HttpException('Envía solicitud_id positivo, latitud y longitud numéricas.', 400);
        }
        $result = (new RequestModel())->saveGeolocalizacion(
            $id, $lat, $lng, (int) $auth->getUserId(), $_SERVER['REMOTE_ADDR'] ?? null
        );
        $this->json($result, $result['success'] ? 200 : 400);
    }

    public function today(): void
    {
        $auth = new Auth();
        $auth->requireRole([ROL_ENRUTADOR, ROL_ADMINISTRADOR, ROL_CALIDAD, ROL_VENTANILLA]);
        $filters = [];
        foreach (['comuna', 'estado'] as $key) {
            $filters[$key] = isset($_GET[$key])
                ? filter_var($_GET[$key], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : null;
            if ($filters[$key] === false) {
                throw new HttpException('Los filtros comuna y estado deben ser enteros positivos.', 400);
            }
        }
        $data = (new RequestModel())->getSolicitudesHoy($filters['comuna'], $filters['estado']);
        $this->json(['success' => true, 'fecha' => date('Y-m-d'), 'total' => count($data), 'data' => $data]);
    }
}
