<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Transactions;
use App\Repository\UserRepository;
use App\Repository\TransactionsRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

class BotCommandController extends AbstractController
{
    private $client;
    private $userRepository;
    private $transactionsRepository;
    private $passwordEncoder;

    public function __construct(HttpClientInterface $client, UserRepository $userRepository, TransactionsRepository $transactionsRepository, UserPasswordEncoderInterface $passwordEncoder)
    {
        $this->client = $client;
        $this->userRepository = $userRepository;
        $this->transactionsRepository = $transactionsRepository;
        $this->passwordEncoder = $passwordEncoder;
    }

    private function getSymbols()
    {
        $api_key = $_SERVER["FIXER_API_KEY"];
        $response = $this->client->request('GET', "http://data.fixer.io/api/symbols?access_key=$api_key");
        $json = json_decode($response->getContent());

        if (!$json->success) {
            $code = $json->error->code;
            $data = $json->error->info ??  $json->error->type;
            $output = "Error($code): $data";

            return $output;
        }

        return get_object_vars($json->symbols);
    }

    private function processAmount($from, $to, $amount, &$convertion = null)
    {
        $api_key = $_SERVER["FIXER_API_KEY"];
        $symbols = $this->getSymbols();
        $output = null;

        if (!isset($symbols[$from])) {
            $output = "ERROR: \"$from\" currency not found";
        } elseif (!isset($symbols[$to])) {
            $output = "ERROR: \"$to\" currency not found";
        }

        if ($output) return $output;

        $url = "http://data.fixer.io/api/latest?access_key=$api_key&symbols=$from,$to&format=1";
        $response = $this->client->request('GET', $url);
        $json = json_decode($response->getContent());

        if ($json->success) {
            $convertion = ($json->rates->$to / $json->rates->$from) * $amount;
            $output = "$from $amount = $to $convertion";
        } else {
            $code = $json->error->code;
            $data = $json->error->info ??  $json->error->type;
            $output = "Error($code): $data";
        }

        return $output;
    }

    private function processAsCurrency($command): ?string
    {
        $count_match = preg_match("/^([\d.]+)\s*(\w{3})\s\w*\s?(\w{3})/i", $command, $matches);

        if ($count_match) {
            $amount = $matches[1];
            $from = strtoupper($matches[2]);
            $to = strtoupper($matches[3]);
        } else {
            $count_match = preg_match("/^(\w{3})\s?([\d.]+)\s\w*\s?(\w{3})/i", $command, $matches);

            if ($count_match) {
                $amount = $matches[2];
                $from = strtoupper($matches[1]);
                $to = strtoupper($matches[3]);
            }
        }

        if (!$count_match) return null;

        return $this->processAmount($from, $to, $amount);
    }

    private function processAsDeposit($command): ?string
    {
        $output = null;
        $count_match = preg_match("/^deposit\s?([\d.]+)\s?([\w]{3})/i", $command, $matches);

        if ($count_match) {
            $amount = $matches[1];
            $symbol = strtoupper($matches[2]);
        } else {
            $count_match = preg_match("/^deposit\s?([\w]{3})\s?([\d.]+)/i", $command, $matches);

            if ($count_match) {
                $amount = $matches[2];
                $symbol = strtoupper($matches[1]);
            }
        }

        if (!$count_match) return null;

        if (!$this->getUser()) return "User is not logged in";

        if ($this->getUser()->getSymbol() != $symbol) {
            $from = $symbol;
            $to = $this->getUser()->getSymbol();

            $this->processAmount($from, $to, $amount, $convertion);
            $amount = $convertion;
        }

        $this->getUser()->setMoney($this->getUser()->getMoney() + $amount);
        $this->userRepository->saveUser($this->getUser());

        $output = "Deposit complete! 📈";
        return $output;
    }

    private function processAsWithdraw($command): ?string
    {
        $count_match = preg_match("/withdraw\s?([\d.]+)\s?([\w]{3})/i", $command, $matches);

        if ($count_match) {
            $amount = $matches[1];
            $symbol = strtoupper($matches[2]);
        } else {
            $count_match = preg_match("/withdraw\s?([\w]{3})\s?([\d.]+)/i", $command, $matches);

            if ($count_match) {
                $amount = $matches[2];
                $symbol = strtoupper($matches[1]);
            }
        }

        if (!$count_match) return null;
        if (!$this->getUser()) return "User is not logged in";

        if ($this->getUser()->getSymbol() != $symbol) {
            $from = $symbol;
            $to = $this->getUser()->getSymbol();

            $this->processAmount($from, $to, $amount, $convertion);
            $amount = $convertion;
        }

        if ($this->getUser()->getMoney() >= $amount) {
            $this->getUser()->setMoney($this->getUser()->getMoney() - $amount);
            $this->userRepository->saveUser($this->getUser());

            return "Withdraw complete! 📉";
        }

        return "Not enough money ⛔";
    }

    private function processAsBalance($command): ?string
    {
        if (preg_match("/^\s*balance\s*\$/i", $command)) {
            if (!$this->getUser()) return "User is not logged in";

            $amount = $this->getUser()->getMoney();
            $symbol = $this->getUser()->getSymbol();
            return "Your current balance is: $symbol $amount";
        }
        return null;
    }

    private function processAsRegister($command): ?string
    {
        if (preg_match("/^\s*register\s*([\w\-\.]+@(?:[\w\-]+\.)+[\w\-]{2,4})\s([\w\s]+)\s(\w{3})/i", $command, $matches)) {
            $symbols = $this->getSymbols();

            $email = $matches[1];
            $password = $matches[2];
            $symbol = strtoupper($matches[3]);

            if (!isset($symbols[$symbol])) return "ERROR: \"$symbol\" currency not found";
            if ($user = $this->userRepository->findOneBy(['email' => $email])) return "Account already exits ⛔";

            $user = new User();
            $user->setRoles($user->getRoles())
                ->setSymbol($symbol)
                ->setEmail($email)
                ->setMoney(0)
                ->setPassword($this->passwordEncoder->encodePassword($user, $password));
            $this->userRepository->saveUser($user);

            return "Your account has been created successfully ✔";
        }
        return null;
    }

    private function processAsSetCurrency($command): ?string
    {
        if (preg_match("/^set\s*currency\s*(\w{3})/i", $command, $matches)) {
            if (!$this->getUser()) return "User is not logged in";

            $symbols = $this->getSymbols();
            $symbol = strtoupper($matches[1]);

            if (!isset($symbols[$symbol])) return "ERROR: \"$symbol\" currency not found";

            $this->processAmount($this->getUser()->getSymbol(), $symbol, $this->getUser()->getMoney(), $amount);

            $this->getUser()
                ->setSymbol($symbol)
                ->setMoney($amount);
            $this->userRepository->saveUser($this->getUser());

            return "Currency updated successfully ✔";
        }
        return null;
    }

    /**
     * @Route("/processcommand")
     */
    public function processCommand(Request $request): Response
    {
        $command = $request->get("command");

        $output = $this->processAsCurrency($command);
        !$output && $output = $this->processAsDeposit($command);
        !$output && $output = $this->processAsWithdraw($command);
        !$output && $output = $this->processAsBalance($command);
        !$output && $output = $this->processAsRegister($command);
        !$output && $output = $this->processAsSetCurrency($command);

        $transactions = new Transactions();
        $transactions->setCommand($command);
        $transactions->setUser($this->getUser());
        $transactions->setOutput($output ?? "");
        $this->transactionsRepository->saveTransaction($transactions);

        return $this->render('chat/response.html.twig', [
            "output" => $output ?? "😶 Oops... your message is not recognized as a command"
        ]);
    }
}
