<?php

namespace BookingBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;

class DefaultController extends Controller
{
    public function indexAction()
    {
        return $this->render('BookingBundle:Default:index.html.twig');
    }

    public function getByRoomAction($email = null)
    {
        return $this->get('booking.exchange_ews')->isRoomAvailable($email);
        /*return new Response(
            json_encode($data),
            200,
            array('Content-Type' => 'application/json')
        );*/
    }

    public function getDetailByRoomAction($email = null)
    {
        return $this->get('booking.exchange_ews')->getBookingDetailByRoom($email);
    }
    public function isRoomAvailableAction($email = null)
    {
        return new Response(
            json_encode([
                'is_room_available' => $this->get('booking.exchange_ews')->isRoomAvailable($email),
                'email_room' => $email,
            ]),
            200,
            array('Content-Type' => 'application/json')
        );
    }
}
