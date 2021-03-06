<?php


namespace App\Controllers;


use App\Helpers\Utils;
use App\Helpers\Validator;
use App\Models\Entities\ActivityDeal;
use App\Models\Entities\Countries;
use App\Models\Entities\Deal;
use App\Models\Entities\Transaction;
use App\Models\Entities\User;
use App\Services\Email;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class TransactionsController extends Controller
{
    public function transactions(Request $request, Response $response)
    {
        $user = $this->getLogged();
        if ($user->getType() != 1) $this->redirect();
        $today = date('Y-m-d');
        $activities = $this->em->getRepository(ActivityDeal::class)->totalCalendar(0, $user, $today);
        $deals = $this->em->getRepository(Deal::class)->findBy(['responsible' => $user->getId(), 'type' => 0], ['name' => 'asc']);
        $countries = $this->em->getRepository(Countries::class)->findBy([], ['name' => 'asc']);
        $users = $this->em->getRepository(User::class)->findBy(['type' => 4, 'active' => 1], ['name' => 'asc']);
        return $this->renderer->render($response, 'default.phtml', ['page' => 'transactions/index.phtml', 'menuActive' => ['transactions'],
            'user' => $user, 'deals' => $deals, 'countries' => $countries, 'users' => $users, 'activities' => $activities]);
    }

    public function saveTransactions(Request $request, Response $response)
    {
        try {
            $user = $this->getLogged();
            $data = (array)$request->getParams();
            $data['transactionId'] ?? 0;
            $id = $data['user'];
            $us = $this->em->getRepository(User::class)->find($id);
            $country = $us->getCountry()->getId();
            $fields = [
                'user' => 'User'
            ];
            Validator::requireValidator($fields, $data);
            $message = new Transaction();
            if ($data['transactionId'] > 0) {
                $message = $this->em->getRepository(Transaction::class)->find($data['transactionId']);
            }
            $message->setWithdrawals(Utils::saveMoney($data['withdrawals']))
                ->setDeposit(Utils::saveMoney($data['deposit']))
                ->setUser($this->em->getReference(User::class, ($data['user'])))
                ->setResponsible($user)
                ->setCountry($this->em->getReference(Countries::class, $country));
            $managers = $this->em->getRepository(User::class)->findBy(['type' => 3]);
            foreach ($managers as $manager) {
                $msg = "<p>Dear {$manager->getName()}.</p>
                    <p>The Leader Board has been updated with new numbers!</p>
                    <p>Sent by Excent Capital</p>";
                Email::send($manager->getEmail(), $manager->getName(), 'Leader Board', $msg);
            }
            $this->em->getRepository(Transaction::class)->save($message);
            return $response->withJson([
                'status' => 'ok',
                'message' => 'Successfully registered transaction!',
            ], 201)
                ->withHeader('Content-type', 'application/json');
        } catch (\Exception $e) {
            return $response->withJson(['status' => 'error',
                'message' => $e->getMessage(),])->withStatus(500);
        }
    }
}