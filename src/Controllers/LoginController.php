<?php


namespace App\Controllers;


use App\Helpers\Session;
use App\Helpers\Utils;
use App\Helpers\Validator;
use App\Models\Entities\AccessLog;
use App\Models\Entities\User;
use App\Models\Entities\RecoverPassword;
use App\Services\Email;
use \Psr\Http\Message\ResponseInterface as Response;
use \Psr\Http\Message\ServerRequestInterface as Request;

class LoginController extends Controller
{

    public function login(Request $request, Response $response)
    {
        if (Session::get('sisgg')) {
            $this->redirect();
        }
        return $this->renderer->render($response, 'login/index.phtml');
    }

    public function autentication(Request $request, Response $response)
    {
        try {
            $data = (array)$request->getParams();
            $fields = [
                'email' => 'Email',
                'password' => 'Password',
            ];
            Validator::requireValidator($fields, $data);
            $user = $this->em->getRepository(User::class)->login($data['email'], $data['password']);
            Session::set('sisgg', $user->getId());
            $this->newAccessLog($user);
            $redirect = Session::get('redirect');
            if ($redirect) {
                Session::forgot('redirect');
                $redirect = substr($redirect, 0, 1) == '/' ? substr($redirect, 1) : $redirect;
                $this->redirect($redirect);
                exit;
            }
        } catch (\Exception $e) {
            Session::set('errorMsg', $e->getMessage());
            header("Location: {$this->baseUrl}login");
            exit;
        }
        header("Location: {$this->baseUrl}");
        exit;
    }

    private function newAccessLog(User $user): AccessLog
    {
        $acessData = Utils::getAcessData();
        $accessLog = new AccessLog();
        $accessLog->setUser($user)
            ->setIp($acessData['ip'])
            ->setDevice($acessData['name'])
            ->setVersion($acessData['version'])
            ->setSo($acessData['platform']);
        $accessLogRepository = $this->em->getRepository(AccessLog::class);
        return $accessLogRepository->save($accessLog);
    }

    public function logout(Request $request, Response $response)
    {
        Session::forgot('sisgg');
        header("Location: {$this->baseUrl}login");
        exit;
    }


    public function recover(Request $request, Response $response)
    {
        return $this->renderer->render($response, 'login/recover.phtml');
    }

    public function changePassword(Request $request, Response $response)
    {
        $id = $request->getAttribute('route')->getArgument('id');
        $valid = true;
        $recover = $this->em->getRepository(RecoverPassword::class)->findOneBy(['token' => $id, 'used' => 0]);
        if (!$recover) {
            $valid = false;
        }
        return $this->renderer->render($response, 'login/change-password.phtml', ['id' => $id, 'valid' => $valid]);
    }

    public function savePassword(Request $request, Response $response)
    {
        try {
            $data = (array)$request->getParams();
            $recover = $this->em->getRepository(RecoverPassword::class)->findOneBy(['token' => $data['id'], 'used' => 0]);
            if (!$recover) {
                throw new \Exception('Token Invalid.');
            }
            $fields = [
                'password2' => 'Confirm the Password',
                'password' => 'Password',
            ];
            Validator::requireValidator($fields, $data);
            Validator::validatePassword($data);
            $user = $recover->getUser();
            $user->setPassword(password_hash($data['password'], PASSWORD_ARGON2I));
            $this->em->getRepository(User::class)->save($user);
            $recover->setUsed(1);
            $this->em->getRepository(RecoverPassword::class)->save($recover);
            return $response->withJson([
                'status' => 'ok',
                'message' => 'Password changed successfully!',
            ], 201)
                ->withHeader('Content-type', 'application/json');
        } catch (\Exception $e) {
            return $response->withJson([
                'status' => 'error',
                'message' => $e->getMessage(),
            ])->withStatus(500);
        }
    }


    public function saveRecover(Request $request, Response $response)
    {
        try {
            $data = (array)$request->getParams();
            $fields = [
                'email' => 'Email',
            ];
            Validator::requireValidator($fields, $data);
            $user = $this->em->getRepository(User::class)->findOneBy(['email' => $data['email'], 'active' => 1]);
            if (!$user) {
                throw new \Exception('Invalid email.');
            }
            $recoverPassword = new RecoverPassword();
            $recoverPassword->setUser($user)
                ->setToken(Utils::generateToken())
                ->setUsed(false);
            $this->em->getRepository(RecoverPassword::class)->save($recoverPassword);
            $msg = "<p>Dear {$user->getName()}.</p>
                    <p>Please see below <a href='{$this->baseUrl}recover/{$recoverPassword->getToken()}' target='_blank'>link</a> to reset your password.</p>
                    <p>Sent by Excent Capital</p>";
            Email::send($user->getEmail(), $user->getName(), 'Password Recovery', $msg);
            return $response->withJson([
                'status' => 'ok',
                'message' => 'An email has been sent to reset your password.',
            ], 201)
                ->withHeader('Content-type', 'application/json');
        } catch (\Exception $e) {
            return $response->withJson([
                'status' => 'error',
                'message' => $e->getMessage(),
            ])->withStatus(500);
        }
    }
}