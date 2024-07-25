<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use App\Service\Wx;
use App\Entity\Feedback;

#[Route('/api')]
class ApiController extends AbstractController
{
    #[Route('/feedback', methods: ['POST'])]
    public function feedback(Request $request): Response
    {
        $params = $request->toArray();
        $firstname = $params['firstname'];
        // $lastname = $params['lastname'];
        $phone = $params['phone'];
        $email = $params['email'];
        $title = $params['title'];
        $body = $params['body'];
        // $country = $params['country'];
        
        $em = $this->data->getEntityManager();
        $f = new Feedback();
        $f->setFirstname($firstname);
        // $f->setLastname($lastname);
        $f->setPhone($phone);
        $f->setEmail($email);
        $f->setTitle($title);
        $f->setBody($body);
        // $f->setCountry($country);
        $em->persist($f);
        $em->flush();

        $data = [
            'code' => 0,
            'msg' => 'ok',
        ];

        return $this->json($data);
    }

    #[Route(path: '/wxconfig', name: 'api_wx_config', methods: ['GET'])]
    public function wxConfig(Wx $wx)
    {
        $this->appid = $_ENV['WX_APP_ID'];
        $this->secret = $_ENV['WX_APP_SECRET'];

        $nonce = '123';
        $timestamp = (new \DateTimeImmutable())->getTimestamp();
        $ticket = $wx->getJSTicket();
        $url = 'https://susong.itove.com';

        $sig = $wx->getJSSignature($ticket, $nonce, $timestamp, $url);

        $data = [
            'appId' => $_ENV['WX_APP_ID'],
            'timestamp' => $timestamp,
            'nonceStr' => $nonce,
            'signature' => $sig,
        ];

        return $this->json($data);
    }
}
