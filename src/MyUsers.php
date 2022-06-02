<?php

namespace RumoPackage;

use App\Models\UsuarioModel;


class MyUsers extends BaseController
{
	public function list()
	{
		$session = \Config\Services::session();

		echo permissao("usuario", true);
		$data['tituloPagina'] = ucfirst(customWord('usuário', true));
		$data['subtituloPagina'] = "Lista";

		$usuarios = new UsuarioModel();
		$usuario = dataToken($session->accessToken);

		$user = $usuarios
			->select('usuario.*, perfil.tipo as perfilTipo')
			->where('usuario.code', $usuario['token']['codeUsuario'])
			->join('perfil', 'perfil.code = usuario.perfil', 'left')->first();

		$usuarios->where('usuario.codeEmpresa', CODEEMPRESA);
		$usuarios->where('perfil <>', '1');
		if (isset($user->perfilTipo) && $user->perfilTipo == 'admin') :
			$usuarios->where('perfil <>', '1');
		endif;
		// remove o super administrador da lista

		// aplica filtros se existir
		if ($session->filtroUsuario) {

			foreach ($session->filtroUsuario as $k => $v) {

				$usuarios->where($k, $v);
			}
		}

		// todos usuarios da pesquisa
		$data['usuarios'] = $usuarios->findAll();

		// colunas que estarão disponiveis para exibição
		$colunas = array(
			'code' => '#REF',
			'nome' => 'Nome',
			'email' => 'E-mail',
			'telefone' => 'Telefone',
			'codeFilial' => 'Filial',
			'perfil' => 'Perfil'
		);

		// pega os campos extras da tabela Cliente
		if ($camposExtras = camposExtras('usuario')) :

			foreach ($camposExtras as $ce) :

				$colunasExtras[slug($ce)] = $ce;
			endforeach;

		else :
			$colunasExtras = array();
		endif;

		$data['colunas'] = array_merge($colunas, $colunasExtras);

		$data['camposExtras'] = $camposExtras;

		return template('modules/usuario/index', $data);
	}
}
