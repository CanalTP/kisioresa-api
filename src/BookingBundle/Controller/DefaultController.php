<?php

namespace BookingBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class DefaultController extends Controller
{
    public function indexAction()
    {
        return $this->render('BookingBundle:Default:index.html.twig');
    }

    public function getByRoomAction($email = null)
    {
        return $this->get('booking.exchange_ews')->getBookingByRoom($email);
        /*return new Response(
            json_encode($data),
            200,
            array('Content-Type' => 'application/json')
        );*/
    }

    public function getDetailByRoom($email = null)
    {
        return $this->get('booking.exchange_ews')->getBookingDetailByRoom($email);
    }
}
