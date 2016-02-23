<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Period;
use AppBundle\Entity\User;
use DateInterval;
use DateTime;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class DefaultController extends Controller {

    private $weekDays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

    /**
     * @Route("/", name="homepage")
     */
    public function indexAction(Request $request) {
        // replace this example code with whatever you need
        return $this->render('default/index.html.twig', [
                    'base_dir' => realpath($this->container->getParameter('kernel.root_dir') . '/..'),
        ]);
    }

    /**
     * @Route("/slack", name="slack")
     */
    public function slackAction(Request $request) {
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

        $response = "All set, " . $args['user_name'] . "\n";

        $text = strtolower($args['text']);
        if (preg_match_all('/([0-9]*[a-z]+(?:[0-9]+(?: ?- ?[a-z]+[0-9]+)?)?)/', $text, $matches) > 0) {
            switch ($matches[1][0]) {
                case 'home':
                case 'office':
                    $user->setPresence($this->setDays($user->getPresence(), $matches[1]));
                    $response .= $this->people();
                    $this->showUpdate($user);
                    break;
                case 'set':
                    $response .= $this->getPeriod($user, $matches[1]);
                    $response .= $this->people();
                    if ($request->getMethod() !== 'GET') {
                        $this->showUpdate($user);
                    }
                case 'people':
                case 'list':
                case 'show':
                    $response = $this->people();
                    break;
                case 'compact':
                    $response = $this->people(null, [ 'mode' => "compact"]);
                    break;
                case '2weeks':
                    $response = $this->people(null, [ 'mode' => 'compact', 'size' => "2weeks"]);
                    break;
                case 'month':
                    $response = $this->people(null, [ 'mode' => 'compact', 'size' => "month"]);
                    break;
                default:
                    $response = "Quick Help:\n"
                        . "- *Regular schedule* (home/office)\n"
                        . "  Set your home or office days:\n"
                        . "     `home|office [mon|tue|wed|thu|fri] ..`\n"
                        . "  (if no weekday informed, current weekday is used)\n"
                        . "- *Special Schedule* (one-time change home/office or\n"
                        . "  other events):\n"
                        . "     `set <event_name>: [mon|tue|wed|thu|fri|xxx99|xxx99-xxx99] ..`\n"
                        . "  (re-run same command to undo/change)\n"
                        . "- *Consultations*\n"
                        . "     `people` (current week, with days/dates)\n"
                        . "     `compact` (same, 1 char columns)\n"
                        . "     `2weeks` (current and next week + weekends, compact)\n"
                        . "     `month` (one month from this week on, compact)\n"
                        . "- *Note*\n"
                        . "  Outside the #presence channel, prefix your command with `/presence`, you'll be the only one to see the command output.\n";
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
    private function getPeriod(&$user, $values) {
        $response = '';
        $weekDays = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
        $months = ['jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec'];
        $days = false;
        $today = date("N") - 1;
        array_shift($values);
        for ($i = 1; $i < count($values); $i++) {
            $pos = array_search(substr($values[$i], 0, 3), $weekDays);
            if ($pos !== false && $pos < $today) {
                $response .= "Note: [" . $values[$i] . "] -> you cannot change days before today this week\n";
                continue;
            }
            if ($pos !== false) {
                $start = new DateTime();
                $start->setTime(0, 0, 0);
                $interval = new DateInterval("P" . ($pos - $today) . "D");
                $start->add($interval);
                $stop = clone($start);
                $stop->setTime(23, 59, 59);
            } else if ($pos === false && preg_match('/([a-z]+)([0-9]+)(?: ?- ?([a-z]+)([0-9]+))?/', $values[$i], $dates) > 0) {
                $startMonth = array_search(substr($dates[1], 0, 3), $months);
                $startDay = $dates[2];
                if (count($dates) < 4) {
                    $dates[3] = $dates[1];
                    $dates[4] = $dates[2];
                }
                $stopMonth = array_search(substr($dates[3], 0, 3), $months);
                $stopDay = $dates[4];
                if ($startMonth === false || $stopMonth === false || !$startDay || $startDay > 31 || !$stopDay || $stopDay > 31) {
                    $response .= "Note: [" . $values[$i] . "] -> I don't get it...\n";
                    continue;
                }
                $year = date("Y");
                $start = new Datetime();
                $start->setDate($year, $startMonth + 1, $startDay);
                $start->setTime(0, 0, 0);
                $stop = new Datetime();
                $stop->setDate($year, $stopMonth + 1, $stopDay);
                $stop->setTime(23, 59, 59);
            } else {
                $response .= "Note: [" . $values[$i] . "] -> what do you mean?\n";
                continue;
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

    private function setDays($presence, $values) {
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
            } else if ($values[0] == 'office') {
                $newPresence &= ~pow(2, $pos);
            }
        }
        if (!$days) {
            $pos = date("N") - 1;
            if ($values[0] == 'home') {
                $newPresence |= pow(2, $pos);
            } else if ($values[0] == 'office') {
                $newPresence &= ~pow(2, $pos);
            }
        }
        return $newPresence;
    }

    /**
     *
     * @param type $size
     * @return string
     */
    private function separator($size, $weeks) {
        $result = '+------------+';
        for ($i = 0; $i < $weeks; $i++) {
            foreach ($this->weekDays as $j => $day) {
                if ($weeks == 1 && $j > 4) {
                    continue;
                }
                $result .= str_repeat('-', $size + 2) . '+';
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
    private function getHeader($size, $weeks) {
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
    private function getWeekStart($today) {
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
    private function people($user = null, array $options = []) {
        $options = array_merge([
            'mode' => 'full',
            'size' => 'week',
                ], $options);
        $cellSize = $options['mode'] == 'full' ? 9 : 1;
        $userRepository = $this->getDoctrine()->getRepository('AppBundle:User');
        $holidayRepository = $this->getDoctrine()->getRepository('AppBundle:Holiday');

        switch ($options['size']) {
            case 'week':
                $weeks = 1;
                break;
            case '2weeks':
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
        $userList = $user ? [ $user] : $userRepository->findBy([], ['name' => 'ASC']);
        $users = 0;

        $today = date("N") - 1;
        $weekStart = $this->getWeekStart($today);
        foreach ($userList as $user) {
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
                    $newStatus = $options['mode'] == "full" ? $holiday->getName() : "*";
                    $foundPeriod = true;
                } else {
                    foreach ($user->getPeriods() as $period) {
                        if ($period->getStart() <= $day && $period->getStop() > $day) {
                            $newStatus = strtoupper($period->getType());
                            $foundPeriod = true;
                            break;
                        }
                    }
                }
                if (!$foundPeriod && $i % 7 < 5) {
                    if (pow(2, $i % 7) & $user->getPresence()) {
                        $newStatus = "HOME";
                    } else {
                        $newStatus = "OFFICE";
                        $office[$i % 7] ++;
                    }
                } else if (!$foundPeriod) {
                    $newStatus = "-";
                }
                if ((true || $today < $i - 1 || $today >= $i + 1) && ($status == "" || $newStatus == $status)) {
                    $days++;
                } else {
                    $showStatus = substr($status, 0, $cellSize + ($cellSize + 3) * ($days - 1));
                    $size = ($cellSize + 3) * $days - 1;
                    $start = floor(($size - strlen($showStatus)) / 2);
                    $end = $size - strlen($showStatus) - $start;
                    if ($days == 1 || $status == "-") {
                        $response .= str_repeat(" ", $start) . $showStatus . str_repeat(" ", $end) . "|";
                    } else {
                        $response .= substr(" <" . str_repeat("-", $start) . " ", 0, $start - 1)
                                . " "
                                . $showStatus
                                . " "
                                . substr(" " . str_repeat("-", $end) . "> ", -$end + 1, $end - 1) . "|";
                    }
                    $days = 1;
                }
                $status = $newStatus;
                $day->add(new DateInterval("P1D"));
            }
            if ($status == $newStatus) {
                $showStatus = substr($status, 0, $cellSize + ($cellSize + 3) * ($days - 1));
                $size = ($cellSize + 3) * $days - 1;
                $start = floor(($size - strlen($showStatus)) / 2);
                $end = $size - strlen($showStatus) - $start;
                if ($days == 1 || $status == "-") {
                    $response .= str_repeat(" ", $start) . $showStatus . str_repeat(" ", $end) . "|";
                } else {
                    $response .= substr(" <" . str_repeat("-", $start) . " ", 0, $start - 1)
                            . " "
                            . $showStatus
                            . " "
                            . substr(" " . str_repeat("-", $end) . "> ", -$end + 1, $end - 1) . "|";
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
                $response .= " " . sprintf(" %2d%% (%2d)", 100 * $office[$i] / $users, $office[$i]) . " |";
            }
            $response .= "\n";
        }
        $response .= $this->separator($cellSize, $weeks) . "```\n";

        return $response;
    }

    private function showUpdate(User $user) {
        $response = $user->getName() . " updated his/her weekly presence:\n";
        $response .= $this->people($user, [ 'mode' => "full" ]);
        $payload = json_encode([
            "text" => $response,
        ]);
        $curl = curl_init($this->getParameter("slack_post_url"));
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => [
                'payload' => $payload,
            ],
            CURLOPT_RETURNTRANSFER => true
        ]);
        $result = curl_exec($curl);
    }

}
