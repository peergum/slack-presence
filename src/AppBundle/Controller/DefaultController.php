<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Period,
    AppBundle\Entity\User,
    DateInterval,
    DateTime,
    Sensio\Bundle\FrameworkExtraBundle\Configuration\Route,
    Symfony\Bundle\FrameworkBundle\Controller\Controller,
    Symfony\Component\HttpFoundation\Request,
    Symfony\Component\HttpFoundation\Response;

class DefaultController extends Controller
{

    private $weekDays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
    private $months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September',
        'October', 'November', 'December'];
    private $mute = false;

    const PERIOD_REGEX = '/([a-z]+) *([0-9]+)?(?: *- *([a-z]+) *([0-9]+)?)?/',
            CMD_PERIOD_REGEX = '/([0-9]*[a-z]+( *[0-9]+)?( *- *[a-z]+( *[0-9]+)?)?)/';

    /**
     * @Route("/", name="homepage")
     */
    public function indexAction(Request $request)
    {
        // replace this example code with whatever you need
        return $this->render('default/index.html.twig',
                        [
                    'base_dir' => realpath($this->container->getParameter('kernel.root_dir') . '/..'),
        ]);
    }

    /**
     * @Route("/test", name="test")
     */
    public function testAction(Request $request)
    {
        $response = json_decode($this->slackAction($request)->getContent(), true);
        return new Response($response['text'], 200, ['content-type' => 'text/plain']);
    }

    /**
     * @Route("/slack", name="slack")
     */
    public function slackAction(Request $request)
    {
        if ($request->getMethod() == 'GET') {
            $args = $request->query->all();
        } else {
            $args = $request->request->all();
        }

        if ($args['token'] != $this->getParameter('slack_command_token') && $args['token'] != $this->getParameter('slack_channel_token')) {
            return new Response('Forbidden', 403);
        }

        $userRepository = $this->getDoctrine()->getRepository('AppBundle:User');
        $user = $userRepository->findOneBy([
            'user' => $args['user_id'],
        ]);
        if (!$user) {
            $user = new User();
            $user->setUser($args['user_id']);
            $user->setName($args['user_name']);
            $user->setLocation('ca');
            $user->setPresence(0);
        }

        $response = '';

        $text = strtolower($args['text']);
        if (preg_match_all(self::CMD_PERIOD_REGEX, $text, $matches) > 0) {
            switch ($matches[1][0]) {
                case 'calendar':
                    $response = $this->calendar();
                    break;
                case 'home':
                case 'office':
                case 'off':
                    $user->setPresence($this->setDays($user->getPresence(), $matches[1]));
                    $response .= $this->people();
                    if (!$this->mute) {
                        $this->showUpdate($user);
                    }
                    break;
                case 'set':
                    $response .= $this->getPeriod($user, $matches[1]);
                    $response .= $this->people();
                    if ($request->getMethod() !== 'GET' && !$this->mute) {
                        $this->showUpdate($user);
                    }
                    break;
                case 'teams':
                    $response = $this->people(null,
                            [
                        'mode' => 'full',
                        'teams' => true,
                    ]);
                    break;
                case 'people':
                    $response = $this->people(null,
                            [
                        'mode' => 'full',
                        'teams' => isset($matches[1][1]) && $matches[1][1] == 'teams',
                    ]);
                    break;
                case 'show':
                    if (!isset($matches[1][1])) {
                        $response .= "No user informed\n";
                        break;
                    }
                    $showUser = $userRepository->findOneBy([
                        'name' => $matches[1][1],
                    ]);

                    if (!$showUser) {
                        $response .= 'I don\'t know user [' . $matches[1][1] . ']';
                        break;
                    }
                    $response = $this->people($showUser,
                            [
                        'mode' => isset($matches[1][2]) ? 'compact' : 'full',
                        'size' => isset($matches[1][2]) ? $matches[1][2] : 'full',
                        'teams' => false,
                    ]);
                    break;
                case 'compact':
                    $response = $this->people(null,
                            [
                        'mode' => "compact",
                        'teams' => isset($matches[1][1]) && $matches[1][1] == 'teams',
                    ]);
                    break;
                case '2weeks':
                case 'weeks':
                    $response = $this->people(null,
                            [
                        'mode' => 'compact',
                        'size' => "weeks",
                        'teams' => isset($matches[1][1]) && $matches[1][1] == 'teams',
                    ]);
                    break;
                case 'month':
                    $response = $this->people(null,
                            [
                        'mode' => 'compact',
                        'size' => "month",
                        'teams' => isset($matches[1][1]) && $matches[1][1] == 'teams',
                    ]);
                    break;
                default:
                    $response = "Quick Help:\n"
                            . "- *Regular schedule* (home/office/off)\n"
                            . "  Set your home, office or not working days:\n"
                            . "     `home|office|off mon|tue|wed|thu|fri ..`\n"
                            . "     _careful_: off = not working day\n"
                            . "  (if no weekday informed, current weekday is used)\n"
                            . "- *Special Schedule* (one-time change home/office or\n"
                            . "  other events):\n"
                            . "     `set <event_name> [next] <day>|<period> ..`\n"
                            . "     with day: `mon|tue|wed|thu|fri|xxx99`\n"
                            . "     and period: day `-` day\n"
                            . "     e.g. `set travel mon`\n"
                            . "          `set travel mon tue wed`\n"
                            . "          `set travel mon-wed`\n"
                            . "          `set travel mon-mar20`\n"
                            . "     use next (once) before days next week\n"
                            . "          `set travel next mon-mar20`\n"
                            . "    (re-run same command to undo/change)\n"
                            . "- *Consultations*\n"
                            . "     `people [teams]` (current week, with days/dates)\n"
                            . "     `compact [teams]` (same, 1 char columns)\n"
                            . "     `weeks [teams]` (current and next week + weekends, compact)\n"
                            . "     `month [teams]` (one month from this week on, compact)\n"
                            . "     `teams` (same as `people teams`)\n"
                            . "     `show <user> [compact|weeks|month]`\n"
                            . "- *Note*\n"
                            . "  Outside the #presence channel, prefix your command with `/presence`, you'll be the only one to see the command output.\n"
                            . "- *Quick calendar for current month*\n"
                            . "    `calendar`\n";
                    break;
            }
        } else {
            $response = "Sorry, I didn't get it... try with `help`";
            return new Response(json_encode([
                        'text' => $response,
                    ]), 200, ['content-type' => 'application/json']);
        }

        $this->getDoctrine()->getManager()->persist($user);
        $this->getDoctrine()->getManager()->flush();

        return new Response(json_encode([
                    'text' => $response,
                ]), 200, ['content-type' => 'application/json']);
    }

    /**
     *
     * @param User $user
     * @param array $args
     * @return string
     */
    private function getPeriod(&$user, $values)
    {
        $response = '';
        $weekDays = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun', 'tom'];
        $months = ['jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec'];
        $days = false;
        $today = date("N") - 1;
        array_shift($values);
        $next = false;
        for ($i = 1; $i < count($values); $i++) {
            if (preg_match(self::PERIOD_REGEX, $values[$i], $dates) == 0) {
                $response .= "Note: [" . $values[$i] . "] -> what do you mean?\n";
                continue;
            } else if ($dates[1] == "next") {
                $next = true;
                continue;
            }
            $start = null;
            $stop = null;
            $pos = array_search(substr($dates[1], 0, 3), $weekDays);
            if ($pos !== false) {
                If ($pos < $today) {
                    $pos+=7;
                } else if ($pos == 7) {
                    // tomorrow
                    $pos = $today + 1;
                }
                $start = new DateTime();
                $start->setTime(0, 0, 0);
                $interval = new DateInterval("P" . ($pos - $today + 7 * $next) . "D");
                $start->add($interval);
                $datePosition = 3;
            } else if (($startMonth = array_search(substr($dates[1], 0, 3), $months)) !== false) {
                $startDay = $dates[2];
                if (!$startDay || $startDay > 31) {
                    $response .= "Wrong start date: [" . $dates[1] . " " . $dates[2] . "]\n";
                    continue;
                }
                $year = date("Y");
                $start = new Datetime();
                $start->setDate($year, $startMonth + 1, $startDay);
                $start->setTime(0, 0, 0);
                $datePosition = 3;
            } else if ($dates[1] == "mute") {
                $this->mute = true;
                continue;
            } else {
                $response .= "What do you mean by [" . $dates[1] . "]...?";
                continue;
            }
            if (count($dates) > $datePosition) {
                $pos2 = array_search(substr($dates[$datePosition], 0, 3), $weekDays);
                if ($pos2 !== false) {
                    while ($pos2 <= $pos) {
                        $pos2+=7;
                    }
                    $stop = new DateTime();
                    $stop->setTime(23, 59, 59);
                    $interval = new DateInterval("P" . ($pos2 - $today) . "D");
                    $stop->add($interval);
                } else if (($stopMonth = array_search(substr($dates[$datePosition], 0, 3), $months)) !== false) {
                    $stopDay = $dates[$datePosition + 1];
                    if (!$stopDay || $stopDay > 31) {
                        $response .= "Wrong end date: [" . $dates[$datePosition] . " " . $dates[$datePosition + 1] . "]\n";
                        continue;
                    }
                    $year = date("Y");
                    $stop = new Datetime();
                    $stop->setDate($year, $stopMonth + 1, $stopDay);
                    $stop->setTime(23, 59, 59);
                } else {
                    $response .= "Not sure what you mean by [" . $dates[$datePosition] . "]";
                    continue;
                }
            }
            if (!$stop) {
                $stop = clone($start);
                $stop->setTime(23, 59, 59);
            }
            $newStart = clone($stop);
            $newStart->add(new \DateInterval('PT1S'));
            $newStop = clone($start);
            $newStop->sub(new \DateInterval('PT1S'));
            $foundPeriod = false;
            foreach ($user->getPeriods() as $period) {
                if ($period->getType() == $values[0] && $start == $period->getStart() && $stop == $period->getStop()) {
                    // same period: remove
                    $foundPeriod = true;
                    $user->removePeriod($period);
                    $this->getDoctrine()->getManager()->remove($period);
                    break;
                } else if ($period->getType() == $values[0] && $period->getStart() <= $start && $period->getStop() >= $stop) {
                    // new period inside: split
                    $foundPeriod = true;
                    if ($period->getStart() == $start) {
                        $period->setStart($newStart);
                        $this->getDoctrine()->getManager()->persist($period);
                    } else if ($period->getStop() == $stop) {
                        $period->setStop($newStop);
                        $this->getDoctrine()->getManager()->persist($period);
                    } else if ($period->getStart() < $start) {
                        $newPeriod = new Period();
                        $newPeriod->setType($values[0]);
                        $newPeriod->setStart($stop);
                        $newPeriod->setStop($period->getStop());
                        $newPeriod->setUser($user);
                        $this->getDoctrine()->getManager()->persist($newPeriod);
                        $period->setStop($start);
                        $user->addPeriod($newPeriod);
                    } else if ($period->getStop() > $stop) {
                        $newPeriod = new Period();
                        $newPeriod->setType($values[0]);
                        $newPeriod->setStart($start);
                        $newPeriod->setStop($period->getStart());
                        $newPeriod->setUser($user);
                        $this->getDoctrine()->getManager()->persist($newPeriod);
                        $period->setStart($stop);
                        $user->addPeriod($newPeriod);
                    }
                    break;
                } else if ($period->getType() == $values[0] && $period->getStart() >= $start && $period->getStop() <= $stop) {
                    $foundPeriod = true;
                    // new period outside: merge
                    $period->setStart($start);
                    $period->setStop($stop);
                    $this->getDoctrine()->getManager()->persist($period);
                } else if ($period->getType() == $values[0] && $period->getStop() == $newStop || $period->getStart() == $newStart) {
                    $foundPeriod = true;
                    // periods overwrite or is adjacent: keep longest
                    if ($start < $period->getStart()) {
                        $period->setStart($start);
                    }
                    if ($stop > $period->getStop()) {
                        $period->setStop($stop);
                    }
                    $this->getDoctrine()->getManager()->persist($period);
                }
            }
            if (!$foundPeriod) {
                $period = new Period();
                $period->setType($values[0]);
                $period->setStart($start);
                $period->setStop($stop);
                $period->setUser($user);
                $user->addPeriod($period);
                $this->getDoctrine()->getManager()->persist($period);
            }
            $this->getDoctrine()->getManager()->persist($user);
            $this->getDoctrine()->getManager()->flush();
        }
        return $response;
    }

    private function setDays($presence, $values)
    {
        $weekDays = ['mon', 'tue', 'wed', 'thu', 'fri'];
        $newPresence = $presence;
        $days = false;
        for ($i = 1; $i < count($values); $i++) {
            $pos = array_search(substr($values[$i], 0, 3), $weekDays);
            if ($pos === false) {
                continue;
            }
            $days = true;
            if ($values[0] == 'home') {
                $newPresence |= pow(2, $pos);
                $newPresence &= ~pow(2, $pos + 5);
            } else if ($values[0] == 'office') {
                $newPresence &= ~pow(2, $pos);
                $newPresence &= ~pow(2, $pos + 5);
            } else if ($values[0] == 'off') {
                $newPresence |= pow(2, $pos + 5);
            }
        }
        if (!$days) {
            $pos = date("N") - 1;
            if ($values[0] == 'home') {
                $newPresence |= pow(2, $pos);
                $newPresence &= ~pow(2, $pos + 5);
            } else if ($values[0] == 'office') {
                $newPresence &= ~pow(2, $pos);
                $newPresence &= ~pow(2, $pos + 5);
            } else if ($values[0] == 'off') {
                $newPresence |= pow(2, $pos + 5);
            }
        }
        return $newPresence;
    }

    /**
     *
     * @param type $size
     * @return string
     */
    private function separator($size, $weeks, $char = '=')
    {
        $result = '+' . str_repeat($char, 12) . '+';
        for ($i = 0; $i < $weeks; $i++) {
            foreach ($this->weekDays as $j => $day) {
                if ($weeks == 1 && $j > 4) {
                    continue;
                }
                $result .= str_repeat($char, $size + 2) . '+';
            }
        }
        $result .= "\n";
        return $result;
    }

    /**
     *
     * @param type $size
     * @return string
     */
    private function getHeader($size, $weeks)
    {
        $today = date("N") - 1;
        $weekStart = $this->getWeekStart($today);
        $currentDay = clone($weekStart);
        $result = $this->separator($size, $weeks);
        $result .= '| Person     |';
        for ($i = 0; $i < $weeks; $i++) {
            foreach ($this->weekDays as $j => $day) {
                if ($weeks == 1 && $j > 4) {
                    continue;
                }
                $theDay = substr($day, 0, $size > 3 ? $size - 3 : $size);
                if ($size > 3) {
                    $theDay .= $currentDay->format(" d");
                }
                $start = floor(($size - strlen($theDay)) / 2);
                $end = $size - $start - strlen($theDay);
                $result .= " " . str_repeat(" ", $start) . $theDay . str_repeat(" ", $end) . " |";
                $currentDay->add(new DateInterval("P1D"));
            }
        }
        $result .= "\n";
        $result .= $this->separator($size, $weeks);
        return $result;
    }

    /**
     *
     * @param int $today
     * @return DateTime
     */
    private function getWeekStart($today)
    {
        $weekStart = new DateTime();
        $weekStart->setTime(0, 0, 0);
        if ($today > 4) {
            $weekStart->add(new DateInterval("P" . (7 - $today) . "D"));
        } else if ($today > 0) {
            $weekStart->sub(new DateInterval("P" . $today . "D"));
        }
        return $weekStart;
    }

    /**
     * @param User|null $user
     * @return string
     */
    private function people($user = null, array $options = [])
    {
        $options = array_merge([
            'mode' => 'full',
            'size' => 'week',
            'teams' => false,
                ], $options);
        $cellSize = $options['mode'] == 'full' ? 9 : 1;
        $userRepository = $this->getDoctrine()->getRepository('AppBundle:User');
        $teamRepository = $this->getDoctrine()->getRepository('AppBundle:Team');
        $holidayRepository = $this->getDoctrine()->getRepository('AppBundle:Holiday');

        switch ($options['size']) {
            case 'week':
                $weeks = 1;
                break;
            case 'weeks':
                $weeks = 2;
                break;
            case 'month':
                $weeks = 4;
                break;
            default:
                $weeks = 1;
                break;
        }

        $response = "```\n" . $this->getHeader($cellSize, $weeks);
        if ($options['teams']) {
            $users = [];
            $teams = $teamRepository->findBy([], ['position' => 'ASC']);
            foreach ($teams as $team) {
                $users = array_merge($users, $team->getUsers()->toArray());
            }
        } else {
            $users = $userRepository->findBy([], ['name' => 'ASC']);
        }
        $userList = $user ? [ $user] : $users;
        $users = 0;

        $today = date("N") - 1;
        $weekStart = $this->getWeekStart($today);
        $team = "";
        foreach ($userList as $user) {
            $userTeam = $user->getTeam() ? $user->getTeam()->getName() : 'NO_TEAM';
            if ($options['teams'] && $team && $team !== $userTeam) {
                $response .= $this->separator($cellSize, $weeks, '=');
            }
            $team = $userTeam;
            $users++;
            $response .= "| " . sprintf("%10s", $user->getName()) . " |";
            $day = clone($weekStart);
            $status = "";
            $days = 0;
            for ($i = 0; $i < 7 * $weeks - 2 * ($weeks == 1); $i++) {
                if (!isset($office[$i])) {
                    $office[$i % 7] = 0;
                }
                $holiday = $holidayRepository->findOneBy([
                    'date' => $day,
                    'location' => $user->getLocation(),
                ]);
                $foundPeriod = false;
                if ($holiday) {
                    $newStatus = $options['mode'] == "full" ? strtoupper($holiday->getName()) : "*";
                    $foundPeriod = true;
                } else {
                    foreach ($user->getPeriods() as $period) {
                        if ($period->getStart() <= $day && $period->getStop() > $day) {
                            $newStatus = strtoupper($period->getType()) . '*';
                            $foundPeriod = true;
                            break;
                        }
                    }
                }
                if (!$foundPeriod && $i % 7 < 5) {
                    if (pow(2, 5 + $i % 7) & $user->getPresence()) {
                        $newStatus = "-";
                    } else if (pow(2, $i % 7) & $user->getPresence()) {
                        $newStatus = "HOME";
                    } else {
                        $newStatus = "OFFICE";
                        $office[$i % 7] ++;
                    }
                } else if (!$foundPeriod) {
                    $newStatus = "-";
                }
                if ($status == "" || $newStatus == $status) {
                    $days++;
                } else {
                    $showStatus = substr($status, 0, $cellSize + ($cellSize + 3) * ($days - 1));
                    $size = ($cellSize + 3) * $days - 1;
                    $start = floor(($size - strlen($showStatus)) / 2);
                    $end = $size - strlen($showStatus) - $start;
                    if ($days == 1 || $status == "-") {
                        $response .= str_repeat(" ", $start) . $showStatus . str_repeat(" ", $end) . "|";
                    } else {
                        $response .= substr(" ." . str_repeat(".", $start), 0, $start)
                                . $showStatus
                                . substr(str_repeat(".", $end) . ". ", -$end, $end) . "|";
                    }
                    $days = 1;
                }
                $status = $newStatus;
                $day->add(new DateInterval("P1D"));
            }
            if ($newStatus == $status) {
                $showStatus = substr($status, 0, $cellSize + ($cellSize + 3) * ($days - 1));
                $size = ($cellSize + 3) * $days - 1;
                $start = floor(($size - strlen($showStatus)) / 2);
                $end = $size - strlen($showStatus) - $start;
                if ($days == 1 || $status == "-") {
                    $response .= str_repeat(" ", $start) . $showStatus . str_repeat(" ", $end) . "|";
                } else {
                    $response .= substr(" ." . str_repeat(".", $start), 0, $start)
                            . $showStatus
                            . substr(str_repeat(".", $end) . ". ", -$end, $end) . "|";
                }
            }
            if ($options['mode'] == 'full') {
                $day = clone($weekStart);
                $day->add(new DateInterval("P1W"));
                foreach ($user->getPeriods() as $period) {
                    if ($period->getStart() <= $day && $period->getStop() > $day) {
                        $newStatus = strtoupper($period->getType());
                        $response .= ($newStatus != $status ? (" " . $newStatus) : '') . " -> " . $period->getStop()->format('M j');
                        break;
                    }
                }
            }

            $response .= "\n";
        }
        if (count($userList) > 1 && $options['mode'] == 'full') {
            $response .= $this->separator($cellSize, $weeks);
            $response .= "| OFFICE --> |";
            for ($i = 0; $i < 5; $i++) {
                $response .= " " . sprintf("%3d%% (%2d)", 100 * $office[$i] / $users, $office[$i]) . " |";
            }
            $response .= "\n";
        }
        $response .= $this->separator($cellSize, $weeks) . "```\n";

        return $response;
    }

    /**
     *
     * @param User $user
     */
    private function showUpdate(User $user)
    {
        $response = $user->getName() . " updated his/her weekly presence:\n";
        $response .= $this->people($user,
                [
            'mode' => 'compact',
            'size' => "month",
        ]);
        $payload = json_encode([
            "text" => $response,
        ]);
        $curl = curl_init($this->getParameter("slack_post_url"));
        curl_setopt_array($curl,
                [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => [
                'payload' => $payload,
            ],
            CURLOPT_RETURNTRANSFER => true
        ]);
        $result = curl_exec($curl);
    }

    private function calendar($options = [])
    {
        $options = array_merge([
            'month' => 'current',
            'size' => 2,
                ], $options);
        $today = date('j');
        $thisMonth = date('n');
        $threeMonths = [];
        if ($thisMonth > 1) {
            $threeMonths[] = $thisMonth - 1;
        }
        $threeMonths[] = $thisMonth;
        if ($thisMonth < 12) {
            $threeMonths[] = $thisMonth + 1;
        }
        $months = $options['month'] == 'current' ?
                $threeMonths : range(1, 12);
        $response = "```\n";
        $width = ($options['size'] + 3) * count($this->weekDays) - 1;
        foreach ($months as $i) {
            $response .= '+' . str_repeat('=', $width) . "+\n";
            $start = floor(($width - strlen($this->months[$i - 1])) / 2);
            $response .= "|" .
                    str_repeat(" ", $start) .
                    $this->months[$i - 1] .
                    str_repeat(" ", $width - $start - strlen($this->months[$i - 1])) . "|\n"
                    . "+";
            foreach ($this->weekDays as $day) {
                $response.=str_repeat("=", $options['size'] + 2) . "+";
            }
            $response .= "\n|";
            foreach ($this->weekDays as $day) {
                $response.=sprintf(" %" . $options['size'] . "s |",
                        substr($day, 0, $options['size']));
            }
            $response .= "\n+";
            foreach ($this->weekDays as $day) {
                $response.=str_repeat("=", $options['size'] + 2) . "+";
            }
            $response .= "\n|";
            $d = 1;
            $date = new DateTime();
            $date->setDate(date('Y'), $i, 1);
            $wday = $date->format('N') - 1;
            for ($j = 0; $j < $wday; $j++) {
                $response .= sprintf(" %" . $options['size'] . "s |", " ");
            }
            while ($date->format('n') == $i) {
                $response .= sprintf(" % " . $options['size'] . "d |", $d);
                $d++;
                $wday = ($wday + 1) % 7;
                $date->add(new DateInterval("P1D"));
                if (!$wday) {
                    if ($i == $thisMonth && $today >= $d && $today < $d + 7) {
                        $response .= "\n+";
                        foreach ($this->weekDays as $day) {
                            $response.=str_repeat("·", $options['size'] + 2) . "+";
                        }
                    }
                    if ($i == $thisMonth && $today >= $d - 7 && $today < $d) {
                        $response .= "\n+";
                        foreach ($this->weekDays as $day) {
                            $response.=str_repeat("·", $options['size'] + 2) . "+";
                        }
                    }
                    $response .= "\n" . ($date->format('n') == $i ? "|" : "");
                }
            };
            for ($j = $wday; $j > 0 && $j < 7; $j++) {
                $response .= sprintf(" %" . $options['size'] . "s |", " ");
            }
            $response .= ($j ? "\n" : "") . "+";
            foreach ($this->weekDays as $day) {
                $response.=str_repeat("=", $options['size'] + 2) . "+";
            }
            $response .= "\n\n";
        }
        $response .= "```";
        return $response;
    }

}
