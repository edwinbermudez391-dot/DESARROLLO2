<?php
declare(strict_types=1);

require_once __DIR__ . '/../../core/Controller.php';
require_once __DIR__ . '/../Models/ComunaModel.php';

class ComunaController extends Controller
{
    public function index(): void
    {
        $this->json(['success' => true, 'data' => (new ComunaModel())->getActive()]);
    }
}
