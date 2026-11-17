<?php
declare(strict_types=1);
require_once __DIR__.'/../../core/Controller.php';
require_once __DIR__.'/../../core/Auth.php';
require_once __DIR__.'/../Models/TerritoryModel.php';
class TerritoryController extends Controller
{
    public function index(): void
    {
        (new Auth())->requireAuth();
        $this->json(['success'=>true]+(new TerritoryModel())->catalog());
    }
}
