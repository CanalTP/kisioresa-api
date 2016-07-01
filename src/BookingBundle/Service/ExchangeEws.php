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

    public function getBookingByRoom($email)
    {
        if (!in_array($email, $this->availableRooms)) {
            throw new InvalidArgumentException(sprintf('The room email %s is not yet implemented or does not exist.'), $email);
        }

        $startDate = new \DateTime('today');
        $endDate = (new \DateTime('today'))->modify('+1 day');

        $request = $this->buildBookingRoomRequest($email, $startDate, $endDate);

        return $this->client->GetUserAvailability($request);
    }

    public function getBookingDetailByRoom($email, \DateTime $startDate, \DateTime $endDate)
    {
        if (!in_array($email, $this->availableRooms)) {
            throw new InvalidArgumentException(sprintf('The room email %s is not yet implemented or does not exist.'), $email);
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
        $request->ParentFolderIds->DistinguishedFolderId->Mailbox->EmailAddress = $email;

        // Send request
        return $this->client->FindItem($request);
    }

    public function isRoomAvailable($email)
    {
        $startDate = new \DateTime('now');
        $endDate = (new \DateTime('now'))->modify('+30 minutes');
        $request = $this->buildBookingRoomRequest($email, $startDate, $endDate, FreeBusyViewType::DETAILED_MERGED);

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

    public function getOccupiedRoomCount() {
        $startDate = new \DateTime('now');
        $endDate = (new \DateTime('now'))->modify('+30 minutes');

        $request = $this->buildBookingRoomRequestFromList($this->availableRooms, $startDate, $endDate, FreeBusyViewType::FREE_BUSY_MERGED);

        $booking = $this->client->GetUserAvailability($request);

        $count = 0;
        foreach ($booking->FreeBusyResponseArray->FreeBusyResponse as $response) {
            if ($response->FreeBusyView->MergedFreeBusy != 0) {
                $count++;
            }
        }

        return $count;
    }
}
