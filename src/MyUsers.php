<?php

namespace RumoPackage;

use App\Models\UsuarioModel;
use App\Models\TabelaModel;
use App\Models\PermissaoModel;
use App\Models\PerfilModel;
use App\Models\Products\ProtecaoVeicular\CategoriaModel;
use App\Models\Products\ProtecaoVeicular\CategoriaUsuarioModel as ProtecaoVeicularCategoriaUsuarioModel;
use CodeIgniter\API\ResponseTrait;
use App\Controllers\BaseController;

class MyUsers extends BaseController
{

	use ResponseTrait;
	public function index()
	{
		echo permissao("usuario", true);
		$data['tituloPagina'] = ucfirst(customWord('usuário', true));
		$data['subtituloPagina'] = "Lista";

		$usuarios = new UsuarioModel();
		$usuario = dataToken($this->session->accessToken);

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
		if (isset($_SESSION['filtroUsuario'])) {

			foreach ($_SESSION['filtroUsuario'] as $k => $v) {

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
	public function detalhe($codeUsuario)
	{
		$data['tituloPagina'] = "Usuário";
		$data['subtituloPagina'] = "Detalhe";

		$usuarios = new UsuarioModel();
		$data['usuario'] = $usuarios->where('code', $codeUsuario)->first();
		if (!$data['usuario']) :
			setSwal('error', 'Ops!', 'Usuário não encontrado');
			return redirect()->to(base_url('usuario'));
		endif;
		$data['usuario']->camposExtras = json_decode($data['usuario']->camposExtras);
		$data['userIf'] = base64_decode($data['usuario']->cpf);



		$categoriaUsuarioModel = new ProtecaoVeicularCategoriaUsuarioModel();
		$categoriaModel = new CategoriaModel();

		$data['categorias'] = $categoriaModel->findAll();

		$categoria_usuario = $categoriaUsuarioModel
			->select('GROUP_CONCAT(codeCategoria) as categorias')
			->where('codeUsuario', $codeUsuario)
			->groupBy('codeUsuario')
			->first();

		// dd($categoria_usuario);

		if (isset($categoria_usuario->categorias)) :
			$data['usuario_categorias'] = explode(',', $categoria_usuario->categorias);
		else :
			$data['usuario_categorias'] = array();
		endif;


		return template('modules/usuario/detalhe', $data);
	}

	public function meus_dados()
	{
		$usuario = usuario();
		$data['tituloPagina'] = $usuario->nome;
		$data['subtituloPagina'] = "Meus dados";

		$data['leadPageLink'] = getEmpresa(CODEEMPRESA, 'leadPageLink');

		$data['usuario'] = myJsonDecode($usuario);

		return template('modules/usuario/perfil', $data);
	}

	public function adicionar()
	{
		$model = new PermissaoModel();
		$data['permissoes'] = $model->findAll();

		$model = new PerfilModel();
		$data['perfis'] = $model->findAll();

		$categoriaModel = new CategoriaModel();

		$data['categorias'] = $categoriaModel->findAll();

		$data['tituloPagina'] = "Usuário";
		$data['subtituloPagina'] = "Adicionar";

		return template('modules/usuario/adicionar', $data);
	}
	public function save()
	{

		$model = new UsuarioModel();

		$data = $this->request->getPost();


		if (isset($data['password'])) :
			if (strlen($data['password']) < 6) :
				if (strlen($data['password']) > 1) :
					setSwal('error', 'Ops!', 'Para gerar uma nova senha ela deve ter no mínimo 6 caracteres.');
				endif;
				unset($data['password']);
			endif;
		endif;
		if (isset($data['code'])) :
			$action = 'update';
		else :
			//$data['code'] = code();
			$action = 'insert';
			$usuario = $model->where('email', $data['email'])
				->first();
			if ($usuario) :
				setSwal('error', 'Ops!', 'Este email já foi cadastrado em outra conta.');
				return redirect()->to(base_url('usuario/adicionar'));
			endif;
		endif;

		$data['codeEmpresa'] = CODEEMPRESA;
		$data['codeFilial'] = (isset($data['codeFilial'])) ? json_encode($data['codeFilial']) : null;
		$data['permissoes'] = (isset($data['permissoes'])) ? json_encode($data['permissoes']) : null;
		$data['camposExtras'] = (isset($data['camposExtras'])) ? json_encode($data['camposExtras']) : null;

		if (!isset($data['code'])) :
			$perfilModel = new PerfilModel();
			$perfil = $perfilModel->find($data['perfil']);
			if ($perfil) :
				$data['perfil'] = $perfil->code;
			else :
				setSwal('error', 'Ops!', 'Não foi selecionado um perfil para o usuário.');
				return redirect()->to(base_url('usuario/adicionar'));
			endif;
		endif;

		if (isset($data['code']) && isset($data['password'])) :
			$data['password_reset'] = 1;
		endif;

		$categoriaUsuarioModel = new ProtecaoVeicularCategoriaUsuarioModel();
		if (isset($data['code']) && isset($data['categoria_usuario'])) :
			$categoriaUsuarioModel->where('codeUsuario', $data['code'])->delete();
		endif;



		$data = array_filter($data);
		if ($action == 'update') :
			$model->save($data);
			setSwal('success', 'Tudo certo!', 'O usuário salvo com sucesso.');
		else :
			$data['code'] = code();
			$model->insert($data);
			setSwal('success', 'Tudo certo!', 'O usuário adicionado com sucesso.');
		endif;

		if (isset($data['categoria_usuario'])) :
			if (isset($data['categoria_usuario'])) :
				foreach ($data['categoria_usuario'] as $codeCategoria) :
					$raw['codeUsuario'] = $data['code'];
					$raw['codeCategoria'] = $codeCategoria;
					$categoriaUsuarioModel->save($raw);
				endforeach;
			endif;
		endif;

		return redirect()->to(backUrl('usuario'));
	}
	public function ativar($code)
	{
		//verificar se é admin
		$model = new UsuarioModel();
		$usuario = $model->where('codeEmpresa', CODEEMPRESA)
			->where('code', $code)
			->first();

		if ($usuario && permissao('statusUsuario')) :
			$data['code'] = $code;
			$data['status'] = 1;
			$model->save($data);

		else :
			setSwal('error', 'Temos um problema', 'Você não tem permissão para executar essa ação.');
		endif;
		return redirect()->to(base_url('usuario/detalhe/' . $code));
	}
	public function desativar($code)
	{
		//verificar se é admin
		$model = new UsuarioModel();
		$usuario = $model->where('codeEmpresa', CODEEMPRESA)
			->where('code', $code)
			->first();
		if ($usuario && permissao('statusUsuario')) :
			$data['code'] = $code;
			$data['status'] = 2;
			$model->save($data);

		else :
			setSwal('error', 'Temos um problema', 'Você não tem permissão para executar essa ação.');
		endif;
		return redirect()->to(base_url('usuario/detalhe/' . $code));
	}
	public function excluir($code)
	{
		//verificar se é admin
		$model = new UsuarioModel();
		$usuario = $model->where('codeEmpresa', CODEEMPRESA)
			->where('code', $code)
			->first();
		if ($usuario && permissao('excluirUsuario')) :
			$model->delete($code);

		else :
			setSwal('error', 'Temos um problema', 'Você não tem permissão para executar essa ação.');
		endif;
		return redirect()->to(base_url('usuario/detalhe/' . $code));
	}
	public function colunas()
	{

		// validar se o usuario logado possui permissao 
		if (isset($_POST['colunas'])) :

			$tabela = new TabelaModel();

			$data['codeUsuario'] = $_SESSION['usuarioCode'];
			$data['campos'] = json_encode($_POST['colunas']);
			$data['tabela'] = strtolower($_POST['tabela']);
			// faz a consulta pelo usuario e a tabela
			$coluna = $tabela->where('codeUsuario', $_SESSION['usuarioCode'])
				->where('tabela', $data['tabela'])
				->first();
			if ($coluna) :
				// se existe faz um update
				$tabela->update($coluna->id, $data);

			else :
				// se não existe cria a linha na tabela
				$tabela->insert($data);
			endif;

		else :
			setSwal('error', 'Ops!', 'Você deve selecionar ao menos uma coluna.');
		endif;
		return redirect()->back();
	}
	public function filtroRapido($tabela, $condicao, $valor)
	{
		$_SESSION['filtro' . ucfirst($tabela)] = array(
			$condicao => $valor
		);

		return redirect()->to(base_url($tabela));
	}
	public function removerFiltro($tabela)
	{
		unset($_SESSION['filtro' . ucfirst($tabela)]);

		return redirect()->back();
	}





	public function csvImport()
	{
		// Validação
		$input = $this->validate([
			'file' => 'uploaded[file]|max_size[file,1024]|ext_in[file,csv],'
		]);
		if (!$input) { // Não é valido
			$data['validation'] = $this->validator;
			setSwal('error', 'Ops!', 'Tipo de arquivo inválido.');
			return redirect()->to(base_url('usuario'));
		} else { // É valido
			if ($file = $this->request->getFile('file')) {
				if ($file->isValid() && !$file->hasMoved()) {
					// Gera um nome aleatório
					$newName = $file->getRandomName();
					// Salva na pasta temp
					$file->move('../public/temp', $newName);
					// Lê o arquivo
					$file = fopen("../public/temp/" . $newName, "r");
					$i = 0;
					$numberOfFields = 5; // Total de campos
					$importData_arr = array();
					// Inicializa a importação
					while (($filedata = fgetcsv($file, 1000, ";")) !== FALSE) {
						$num = count($filedata);
						// Pula a primeira linha & e verifica o numero de campos
						if ($i > 0 && $num == $numberOfFields) {
							// Nome dos campos que devem existir - nome, email, telefone, cpf, codeFilial(json)
							$importData_arr[$i]['codeEmpresa'] = CODEEMPRESA;
							$importData_arr[$i]['nome'] = $filedata[0];
							$importData_arr[$i]['userIf'] = $filedata[1];
							$importData_arr[$i]['telefone'] = $filedata[2];
							$importData_arr[$i]['email'] = $filedata[3];
							$importData_arr[$i]['codeFilial'] = $filedata[4];
							$importData_arr[$i]['perfil'] = 'M2120GK914X262';
							$importData_arr[$i]['permissoes'] = '["gestao-de-vendas","lead","proposta","venda"]';
							$importData_arr[$i]['password'] = substr(soNumero($filedata[2]), -8);
							$importData_arr[$i]['password_reset'] = 1;
						}
						$i++;
					}
					fclose($file);

					// Conexão com o banco
					$usuarioModel = new UsuarioModel();

					foreach ($importData_arr as $insertData) {
						## Insert
						$usuarioModel->insert($insertData);
					}
					// Mensagem de sucesso
					setSwal('success', 'Tudo certo', 'Usuários importados com sucesso!');
				} else {
					// Mensagem de erro
					setSwal('error', 'Ops!', 'Os usuários não foram importados com sucesso.');
				}
			} else {
				// Mensagem de erro
				setSwal('error', 'Ops!', 'Os usuários não foram importados com sucesso.<br> Tipo de arquivo inválido.');
			}
			// Delete file
			unlink("../public/temp/$newName");
		}
		return redirect()->to(base_url('usuario'));
	}
}
