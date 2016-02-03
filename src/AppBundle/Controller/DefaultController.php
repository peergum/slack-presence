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
     * @Route("/slack", name="slack")
     */
    public function slackAction(Request $request)
    {
        $args = $request->query->all();

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
        if (preg_match_all('/([a-z]+)/', $text, $matches) > 0) {
            switch ($matches[0][0]) {
                case 'home':
                case 'office':
                    $user->setPresence($this->setDays($user->getPresence(), $matches[0]));
                    $response .= $this->people();
                    $this->showUpdate($user);
                    break;
                case 'sick':
                case 'away':
                case 'travel':
                    $this->getPeriod($user,$matches[0]);
                    $response .= $this->people();
                    $this->showUpdate($user);
                case 'people':
                case 'list':
                case 'show':
                    $response = $this->people();
                    break;
                case 'compact':
                    $response = $this->peopleCompact();
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
                            . "\nNote: outside the #presence channel, use /schedule before your command\n"
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
     * @param type $periods
     * @param type $values
     * @return Period
     */
    private function getPeriod(&$user, $values) {
        $weekDays = ['mon', 'tue', 'wed', 'thu', 'fri'];
        $days = false;
        $today = date("N")-1;
        for ($i = 1; $i < count($values); $i++) {
            $pos = array_search(substr($values[$i], 0, 3), $weekDays);
            if ($pos === false || $pos < $today) {
                continue;
            }
            $days = true;
            $start = new DateTime();
            $start->setTime(0,0,0);
            $interval = new DateInterval("P".($pos-$today)."D");
            $start->add($interval);
            $stop=clone($start);
            $stop->add(new DateInterval("PT23H59M59S"));
            $foundPeriod = false;
            var_dump($start);
            var_dump($stop);
            foreach($user->getPeriods() as $period) {
                var_dump($period->getStart());
                var_dump($period->getStop());
                if ($period->getType() == $values[0]
                        && $start->diff($period->getStart())->days<=0
                        && $stop->diff($period->getStop())->days>=0) {
                    $foundPeriod = true;
                    echo "FOUND!";
                    break;
                }
            }
            if (!$foundPeriod) {
                $period = new Period();
                $period->setType($values[0]);
                $period->setStart($start);
                $period->setStop($stop);
                $user->addPeriod($period);
            }
        }
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
     * @param User|null $user
     * @return string
     */
    private function people($user = null)
    {
        $userRepository = $this->getDoctrine()->getRepository('AppBundle:User');

        if (!$user) {
            $response = "```\n"
                    . "+------------+-----------+-----------+-----------+-----------+-----------+\n"
                    . "| Person     | Monday    | Tuesday   | Wednesday | Thursday  | Friday    |\n"
                    . "+------------+-----------+-----------+-----------+-----------+-----------+\n";
            $userList = $userRepository->findBy([], ['name' => 'ASC']);
        } else {
            $response = "```\n"
                    . "             +-----------+-----------+-----------+-----------+-----------+\n"
                    . "             | Monday    | Tuesday   | Wednesday | Thursday  | Friday    |\n"
                    . "+------------+-----------+-----------+-----------+-----------+-----------+\n";
            $userList = [ $user ];
        }
        $users = 0;

        $today = date("N")-1;
        $weekStart = new DateTime();
        $weekStart->setTime(0,0,0);
        if ($today>4) {
            $weekStart->add(new DateInterval("P".(7-$today)."D"));
        } else if ($today>0) {
            $weekStart->sub(new DateInterval("P".$today."D"));
        }
        foreach ($userList as $user) {
            $users++;
            $response .= "| " . sprintf("%10s", $user->getName()) . " |";
            $day = clone($weekStart);
            for ($i = 0; $i < 5; $i++) {
                $day->add(new DateInterval("P1D"));
                if (!isset($office[$i])) {
                    $office[$i] = 0;
                }
                $foundPeriod = false;
                foreach ($user->getPeriods() as $period) {
                    if ($day->diff($period->getStart())->days>0
                            && ($day->diff($period->getStop())->days<=0)) {
                        $response .= sprintf(" %-9s |",  ucfirst($period->getType()));
                        $foundPeriod = true;
                        break;
                    }
                }
                if ($foundPeriod) {
                    continue;
                }
                if (pow(2, $i) & $user->getPresence()) {
                    $response .= " Home      |";
                } else {
                    $response .= " Office    |";
                    $office[$i] ++;
                }
            }
            $response .= "\n";
        }
        if (count($userList)>1) {
            $response .= "+------------+-----------+-----------+-----------+-----------+-----------+\n";
            $response .= "| Office --> |";
            for ($i = 0; $i < 5; $i++) {
                $response .= " " . sprintf("%8d%%", 100 * $office[$i] / $users) . " |";
            }
            $response .= "\n";
        }
        $response .= "+------------+-----------+-----------+-----------+-----------+-----------+\n"
                . "```\n";

        return $response;
    }

    /**
     *
     * @return string
     */
    private function peopleCompact()
    {
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

    private function showUpdate(User $user)
    {
        return;
        $response = $user->getName()." updated his/her weekly presence:\n";
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
