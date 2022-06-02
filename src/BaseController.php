<?php

namespace App\Controllers;

use App\Models\AlertaModel;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;


class BaseController extends Controller
{
    protected $helpers = [
        'default',
        'plataforma',
        'alerts'
    ];
    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        // Do Not Edit This Line
        parent::initController($request, $response, $logger);

        setlocale(LC_TIME, 'pt_BR', 'pt_BR.utf-8', 'pt_BR.utf-8', 'portuguese');
        date_default_timezone_set('America/Sao_Paulo');
        // --------------------------------------------------------------------
        // Preload any models, libraries, etc, here.
        // --------------------------------------------------------------------
        // E.g.: $this->session = \Config\Services::session();


        $this->session = \Config\Services::session();


        $this->api = \Config\Services::curlrequest([
            'baseURI' => base_url('api/v1') . '/',
            "headers" => [
                "Accept" => "application/json"
            ]
        ]);

        $this->path = 'products/protecaoveicular';

        $this->alerta = new AlertaModel();

        // checa se esta logado
        checkAuth();
    }
}
