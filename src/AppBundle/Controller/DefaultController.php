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

    private $weekDays = ['Monday', 'Tuesday', 'Wednesday', 'Thrusday', 'Friday'];

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
            $user->setPresence(0);
        }

        $response = "All set, " . $args['user_name'] . "\n";

        $text = strtolower($args['text']);
        if (preg_match_all('/([a-z]+|[a-z]+[0-9]+ ?- ?[a-z]+[0-9]+)/g', $text, $matches) > 0) {
            switch ($matches[1][0]) {
                case 'home':
                case 'office':
                    $user->setPresence($this->setDays($user->getPresence(), $matches[1]));
                    $response .= $this->people();
                    $this->showUpdate($user);
                    break;
                case 'sick':
                case 'away':
                case 'travel':
                    $this->getPeriod($user, $matches);
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
                    $response = $this->people(null, "compact");
                    break;
                default:
                    $response = "```\n"
                            . "Help:\n"
                            . "- Set your home/office/sick/away days:\n"
                            . "  home|office|sick|away|travel: [mon|tue|wed|thu|fri] ..\n"
                            . "  (use sick/away/travel again to revert)"
                            . "  (sick/away/travel with no day informed toggles current day)\n"
                            . "- See everyone's presence:\n"
                            . "  people (use compact on cell)\n"
                            . "\nNote: outside the #presence channel, use /presence before your command\n"
                            . "```\n";
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
     * @param array $values
     * @return Period
     */
    private function getPeriod(&$user, $values) {
        $weekDays = ['mon', 'tue', 'wed', 'thu', 'fri'];
        $months = ['jan','feb','mar','apr','may','jun','jul','aug','sep','oct','nov','dec'];
        $days = false;
        $today = date("N") - 1;
        for ($i = 1; $i < count($values); $i++) {
            $pos = array_search(substr($values[$i], 0, 3), $weekDays);
            if ($pos !== false && $pos < $today) {
                continue;
            }
            if ($pos !== false) {
                $start = new DateTime();
                $start->setTime(0, 0, 0);
                $interval = new DateInterval("P" . ($pos - $today) . "D");
                $start->add($interval);
                $stop = clone($start);
                $stop->setTime(23,59,59);
            } else if ($pos === false && preg_match('/([a-z]+)([0-9]+) ?- ?([a-z]+)([0-9]+)/', $value[$i], $dates) > 0) {
                $startMonth = array_search(substr($dates[1], 0, 3), $months);
                $startDay = $dates[2];
                $stopMonth = array_search(substr($dates[3], 0, 3), $months);
                $stopDay = $dates[4];
                if ($startMonth === false || $stopMonth === false || !$startDay || $startDay>31 || !$stopDay || $stopDay>31 ) {
                    continue;
                }
                $year = date("Y");
                $start = new Datetime();
                $start->setDate($year,$startMonth,$startDay);
                $start->setTime(0,0,0);
                $stop = new Datetime();
                $stop->setDate($year,$stopMonth,$stopDay);
                $stop->setTime(23,59,59);
            } else {
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
    private function separator($size) {
        $result = '+------------+';
        foreach ($this->weekDays as $day) {
            $result .= str_repeat('-', $size + 2) . '+';
        }
        $result .= "\n";
        return $result;
    }

    /**
     * 
     * @param type $size
     * @return string
     */
    private function getHeader($size) {

        $result = $this->separator($size);
        $result = '| Person     |';
        foreach ($this->weekDays as $day) {
            $result .= sprintf(" %-" . $size . "s |", substr($day, 0, $size));
        }
        $result .= "\n";
        $result .= $this->separator($size);
        return $result;
    }

    /**
     * @param User|null $user
     * @return string
     */
    private function people($user = null, $mode = 'full') {
        $cellSize = $mode == 'full' ? 9 : 1;
        $userRepository = $this->getDoctrine()->getRepository('AppBundle:User');

        $response = "```\n" . $this->getHeader($cellSize);
        $userList = $user ? [ $user] : $userRepository->findBy([], ['name' => 'ASC']);
        $users = 0;

        $today = date("N") - 1;
        $weekStart = new DateTime();
        $weekStart->setTime(0, 0, 0);
        if ($today > 4) {
            $weekStart->add(new DateInterval("P" . (7 - $today) . "D"));
        } else if ($today > 0) {
            $weekStart->sub(new DateInterval("P" . $today . "D"));
        }
        foreach ($userList as $user) {
            $users++;
            $response .= "| " . sprintf("%10s", $user->getName()) . " |";
            $day = clone($weekStart);
            $status = "";
            $days = 0;
            for ($i = 0; $i < 5; $i++) {
                if (!isset($office[$i])) {
                    $office[$i] = 0;
                }
                $foundPeriod = false;
                foreach ($user->getPeriods() as $period) {
                    if ($period->getStart() <= $day && $period->getStop() > $day) {
                        $newStatus = ucfirst($period->getType());
                        $foundPeriod = true;
                        break;
                    }
                }
                if (!$foundPeriod) {
                    if (pow(2, $i) & $user->getPresence()) {
                        $newStatus = "Home";
                    } else {
                        $newStatus = "Office";
                        $office[$i] ++;
                    }
                }
                if ($status == "" || $newStatus == $status) {
                    $days++;
                } else {
                    $showStatus = substr($status, 0, $cellSize + ($cellSize + 3) * ($days - 1));
                    $size = ($cellSize + 3) * $days - 1;
                    $start = floor(($size - strlen($showStatus)) / 2);
                    $end = $size - strlen($showStatus) - $start;
                    $response .= str_repeat(" ", $start) . $showStatus . str_repeat(" ", $end) . "|";
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
                $response .= str_repeat(" ", $start) . $showStatus . str_repeat(" ", $end) . "|";
            }
            $response .= "\n";
        }
        if (count($userList) > 1 && $mode == 'full') {
            $response .= $this->separator($cellSize);
            $response .= "| Office --> |";
            for ($i = 0; $i < 5; $i++) {
                $response .= " " . sprintf(" %2d%% (%2d)", 100 * $office[$i] / $users, $office[$i]) . " |";
            }
            $response .= "\n";
        }
        $response .= $this->separator($cellSize) . "```\n";

        return $response;
    }

    /**
     *
     * @return string
     */
    private function peopleCompact() {
        $userRepository = $this->getDoctrine()->getRepository('AppBundle:User');

        $response = "```\n"
                . "+------------+---+---+---+---+---+\n"
                . "| Person     | M | T | W | T | F |\n"
                . "+------------+---+---+---+---+---+\n";
        foreach ($userRepository->findBy([], ['name' => 'ASC']) as $user) {
            $response .= "| " . sprintf("%10s", $user->getName()) . " |";
            for ($i = 0; $i < 5; $i++) {
                if (pow(2, $i + 21) & $user->getPresence()) {
                    $response .= " T |";
                } else if (pow(2, $i + 14) & $user->getPresence()) {
                    $response .= " - |";
                } else if (pow(2, $i + 7) & $user->getPresence()) {
                    $response .= " S |";
                } if (pow(2, $i) & $user->getPresence()) {
                    $response .= " H |";
                } else {
                    $response .= " O |";
                }
            }
            $response .= "\n";
        }
        $response .= "+------------+---+---+---+---+---+\n"
                . "```\n";
        return $response;
    }

    private function showUpdate(User $user) {
        return;
        $response = $user->getName() . " updated his/her weekly presence:\n";
        $response .= $this->people($user);
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
