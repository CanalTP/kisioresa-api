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
    }

    public function getDetailByRoomAction($email = null)
    {
        return $this->get('booking.exchange_ews')->getBookingDetailByRoom($email);
    }

    public function isRoomAvailableAction($email = null)
    {
        $service = $this->get('booking.exchange_ews');

        $dailyAvailability = $service
            ->getBookingByRoom($email)
            ->FreeBusyResponseArray
            ->FreeBusyResponse
            ->FreeBusyView
            ->MergedFreeBusy;

        $isAvailable = $service->isRoomAvailable($email);

        $data = [
            'is_room_available' => $isAvailable,
            'email_room' => $email,
            'daily_availability' => $dailyAvailability,
        ];

        if (!$isAvailable) {
            $startDate = new \DateTime();
            $endDate = (new \DateTime())->modify('+30 minutes');
            $currentMeeting = $service->getBookingDetailByRoom($email, $startDate, $endDate);
            $organizer = $currentMeeting
                ->ResponseMessages
                ->FindItemResponseMessage
                ->RootFolder
                ->Items
                ->CalendarItem
                ->Organizer
                ->Mailbox
                ->Name;

            $data['current_meeting'] = [
                'organizer' => $organizer,
            ];

            $data['suggested_rooms'] = $service->findAvailableRoomsAround($email);
        }

        return new Response(
            json_encode($data),
            200,
            array('Content-Type' => 'application/json')
        );
    }

    public function getOccupiedRoomCountAction()
    {
        $service = $this->get('booking.exchange_ews');

        $service->getOccupiedRoomCount();

        return new Response(
            json_encode($service->getOccupiedRoomCount()),
            200,
            array('Content-Type' => 'application/json')
        );
    }
}
