<?php

namespace BookingBundle\Service;

use DateTime;
use InvalidArgumentException;
use PhpEws\DataType\CalendarViewType;
use PhpEws\DataType\DayOfWeekType;
use PhpEws\DataType\DefaultShapeNamesType;
use PhpEws\DataType\DistinguishedFolderIdNameType;
use PhpEws\DataType\DistinguishedFolderIdType;
use PhpEws\DataType\Duration;
use PhpEws\DataType\EmailAddress;
use PhpEws\DataType\FindItemType;
use PhpEws\DataType\FreeBusyViewOptionsType;
use PhpEws\DataType\FreeBusyViewType;
use PhpEws\DataType\GetUserAvailabilityRequestType;
use PhpEws\DataType\ItemQueryTraversalType;
use PhpEws\DataType\ItemResponseShapeType;
use PhpEws\DataType\MailboxData;
use PhpEws\DataType\MeetingAttendeeType;
use PhpEws\DataType\NonEmptyArrayOfBaseFolderIdsType;
use PhpEws\DataType\SerializableTimeZone;
use PhpEws\DataType\SerializableTimeZoneTime;
use PhpEws\EwsConnection;

class ExchangeEws
{
    /**
     * @var EwsConnection
     */
    private $client = null;

    public function __construct($endpoint, $username, $password, array $availableRooms)
    {
        $this->client = new EwsConnection($endpoint, $username, $password, EwsConnection::VERSION_2010_SP2);
        $this->availableRooms = $availableRooms;
    }

    public function getBookingByRoom($roomMail)
    {
        if (!in_array($roomMail, array_keys($this->availableRooms))) {
            throw new InvalidArgumentException(sprintf('The room email %s is not yet implemented or does not exist.'), $roomMail);
        }

        $startDate = new \DateTime('today');
        $endDate = (new \DateTime('today'))->modify('+1 day');

        $request = $this->buildBookingRoomRequest($roomMail, $startDate, $endDate);

        return $this->client->GetUserAvailability($request);
    }

    public function getBookingDetailByRoom($roomMail, \DateTime $startDate, \DateTime $endDate)
    {
        if (!in_array($roomMail, array_keys($this->availableRooms))) {
            throw new InvalidArgumentException(sprintf('The room email %s is not yet implemented or does not exist.'), $roomMail);
        }

        $request = new FindItemType();

        $request->Traversal = ItemQueryTraversalType::SHALLOW;

        $request->ItemShape = new ItemResponseShapeType();
        $request->ItemShape->BaseShape = DefaultShapeNamesType::DEFAULT_PROPERTIES;

        // Define the timeframe to load calendar items
        $request->CalendarView = new CalendarViewType();
        $request->CalendarView->StartDate = $startDate->format(DATE_W3C);
        $request->CalendarView->EndDate = $endDate->format(DATE_W3C);

        // Only look in the "calendars folder"
        $request->ParentFolderIds = new NonEmptyArrayOfBaseFolderIdsType();
        $request->ParentFolderIds->DistinguishedFolderId = new DistinguishedFolderIdType();
        $request->ParentFolderIds->DistinguishedFolderId->Id = DistinguishedFolderIdNameType::CALENDAR;
        $request->ParentFolderIds->DistinguishedFolderId->Mailbox = new \stdClass;
        $request->ParentFolderIds->DistinguishedFolderId->Mailbox->EmailAddress = $roomMail;

        // Send request
        return $this->client->FindItem($request);
    }

    public function isRoomAvailable($roomMail)
    {
        $startDate = new \DateTime('now');
        $endDate = (new \DateTime('now'))->modify('+30 minutes');
        $request = $this->buildBookingRoomRequest($roomMail, $startDate, $endDate, FreeBusyViewType::DETAILED_MERGED);

        $booking = $this->client->GetUserAvailability($request);

        return 0 == $booking->FreeBusyResponseArray->FreeBusyResponse->FreeBusyView->MergedFreeBusy;
    }

    private function buildBookingRoomRequest(
        $roomMail,
        \DateTime $startDate,
        \DateTime $endDate,
        $requestedView = FreeBusyViewType::DETAILED_MERGED
    ) {
        return $this->buildBookingRoomRequestFromList([$roomMail], $startDate, $endDate, $requestedView);
    }

    private function buildBookingRoomRequestFromList(
        array $roomMailList,
        \DateTime $startDate,
        \DateTime $endDate,
        $requestedView = FreeBusyViewType::DETAILED_MERGED
    ) {
        $request = new GetUserAvailabilityRequestType();
        $request->TimeZone = new SerializableTimeZone();
        $request->TimeZone->Bias = 0;

        $request->TimeZone->StandardTime = new SerializableTimeZoneTime();
        $request->TimeZone->StandardTime->Bias = -60;
        $request->TimeZone->StandardTime->Time = '02:00:00';
        $request->TimeZone->StandardTime->DayOrder = 5;
        $request->TimeZone->StandardTime->Month = 10;
        $request->TimeZone->StandardTime->DayOfWeek = DayOfWeekType::SUNDAY;

        $request->TimeZone->DaylightTime = new SerializableTimeZoneTime();
        $request->TimeZone->DaylightTime->Bias = -120;
        $request->TimeZone->DaylightTime->Time = '02:00:00';
        $request->TimeZone->DaylightTime->DayOrder = 1;
        $request->TimeZone->DaylightTime->Month = 4;
        $request->TimeZone->DaylightTime->DayOfWeek = DayOfWeekType::SUNDAY;

        $request->MailboxDataArray = [];
        foreach ($roomMailList as $roomMail) {
            $mailboxData = new MailboxData();
            $mailboxData->Email = new EmailAddress();
            $mailboxData->Email->Address = $roomMail;
            $mailboxData->Email->RoutingType = 'SMTP';
            $mailboxData->AttendeeType = MeetingAttendeeType::ROOM;
            $mailboxData->ExcludeConflicts = false;
            $request->MailboxDataArray[] = $mailboxData;
        }

        $request->FreeBusyViewOptions = new FreeBusyViewOptionsType();
        $request->FreeBusyViewOptions->TimeWindow = new Duration();
        $request->FreeBusyViewOptions->TimeWindow->StartTime = $startDate->format(DATE_W3C);
        $request->FreeBusyViewOptions->TimeWindow->EndTime = $endDate->format(DATE_W3C);

        $request->FreeBusyViewOptions->MergedFreeBusyIntervalInMinutes = 30;
        $request->FreeBusyViewOptions->RequestedView = $requestedView;

        return $request;
    }

    public function getOccupiedRoomCount()
    {
        $startDate = new \DateTime('now');
        $endDate = (new \DateTime('now'))->modify('+30 minutes');
        
        $request = $this->buildBookingRoomRequestFromList(array_keys($this->availableRooms), $startDate, $endDate, FreeBusyViewType::FREE_BUSY_MERGED);

        $booking = $this->client->GetUserAvailability($request);

        $count = 0;
        foreach ($booking->FreeBusyResponseArray->FreeBusyResponse as $response) {
            if ($response->FreeBusyView->MergedFreeBusy != 0) {
                $count++;
            }
        }

        return $count;
    }

    public function findAvailableRoomsAround($roomMail)
    {
        if (! array_key_exists($roomMail, $this->availableRooms)) {
            throw new InvalidArgumentException(sprintf('The room email %s is not yet implemented or does not exist.'), $roomMail);
        }

        $equivalentRooms = $this->getEquivalentRooms($roomMail);

        $equivalentAvailableRooms = [];

        if (count($equivalentRooms) > 0) {
            $startDate = new \DateTime();
            $endDate = (new \DateTime())->modify('+1 hour');
            $request = $this->buildBookingRoomRequestFromList($equivalentRooms, $startDate, $endDate, FreeBusyViewType::FREE_BUSY_MERGED);

            $booking = $this->client->GetUserAvailability($request);

            if (is_array($booking->FreeBusyResponseArray->FreeBusyResponse)) {
                for ($cpt = 0; $cpt < count($equivalentRooms); $cpt++) {
                    if (0 == $booking->FreeBusyResponseArray->FreeBusyResponse[$cpt]->FreeBusyView->MergedFreeBusy) {
                        $equivalentAvailableRooms[$equivalentRooms[$cpt]] = $this->availableRooms[$equivalentRooms[$cpt]];
                    }
                }
            } else {
                if (0 == $booking->FreeBusyResponseArray->FreeBusyResponse->FreeBusyView->MergedFreeBusy) {
                    $equivalentAvailableRooms[$equivalentRooms[0]] = $this->availableRooms[$equivalentRooms[0]];
                }
            }
            $baseRoomConfig = $this->availableRooms[$roomMail];

            usort($equivalentAvailableRooms, function($a, $b) use ($baseRoomConfig) {
                return (abs($a['floor'] - $baseRoomConfig['floor']) >= abs($b['floor'] - $baseRoomConfig['floor']));
            });
        }

        return $equivalentAvailableRooms;
    }

    private function getEquivalentRooms($baseRoomMail)
    {
        $baseRoomConfig = $this->availableRooms[$baseRoomMail];

        $equivalentRooms = [];
        foreach($this->availableRooms as $roomMail => $roomConfig) {
            if ($roomMail == $baseRoomMail) {
                continue;
            } elseif (
                $roomConfig['places_count'] <= $baseRoomConfig['places_count'] + 2
                && $roomConfig['places_count'] >= $baseRoomConfig['places_count'] - 2
            ) {
                $equivalentRooms[] = $roomMail;
            }
        }

        return $equivalentRooms;
    }
}
