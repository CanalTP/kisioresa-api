<?php

namespace BookingBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;

class DefaultController extends Controller
{
    public function getDetailByRoomAction($email = null)
    {
        $service = $this->get('booking.exchange_ews');

        $isAvailable = $service->isRoomAvailable($email);

        $data = [
            'is_room_available' => $isAvailable,
            'email_room' => $email,
            'daily_availability' => $service->getBookingByRoom($email),
        ];

        if (!$isAvailable) {
            $data['current_meeting'] = [
                'organizer' => $service->getOrganizer($email),
            ];

            $data['suggested_rooms'] = $service->findAvailableRoomsAround($email);
        }

        return new Response(
            json_encode($data),
            200,
            ['Content-Type' => 'application/json']
        );
    }

    public function getOccupiedRoomCountAction()
    {
        return new Response(
            json_encode($this->get('booking.exchange_ews')->getOccupiedRoomCount()),
            200,
            ['Content-Type' => 'application/json']
        );
    }
}
