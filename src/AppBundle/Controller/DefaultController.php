<?php

namespace AppBundle\Controller;

use AppBundle\Entity\User,
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
        $args = $request->request->all();

        if ($args['token'] != $this->getParameter('slack_token')) {
            return new Response('Forbidden',403);
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
            $this->getDoctrine()->getEntityManager()->persist($user);
            $this->getDoctrine()->getEntityManager()->flush();
        }

        $response = "All set, " . $args['user_name'] . "\n";

        $text = strtolower($args['text']);
        if (preg_match_all('/([a-z]+)/', $text, $matches) > 0) {
            switch ($matches[0][0]) {
                case 'home':
                case 'office':
                case 'sick':
                case 'away':
                    $user->setPresence($this->setDays($user->getPresence(), $matches[0]));
                    $this->getDoctrine()->getEntityManager()->persist($user);
                    $this->getDoctrine()->getEntityManager()->flush();
                    $response .= $this->people();
                    break;
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
                        . "  home|office|sick|away: [mon|tue|wed|thu|fri] ..\n"
                        . "  (use sick/away again to revert)"
                        . "  (sick/away with no day informed toggles current day)\n"
                        . "- See everyone's presence:\n"
                        . "  people (use compact on cell)\n"
                        . "\nNote: outside the #presence channel, use /schedule before your command\n"
                        . "```\n";
                    break;
            }
        } else {
            $response = "I didn't get it...";
            return new Response(json_encode([
                        'text' => $response,
            ]),200,['content-type' => 'application/json']);
        }

        return new Response(json_encode([
                    'text' => $response,
        ]),200,['content-type' => 'application/json']);
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
            } else if ($values[0] == 'sick') {
                $newPresence ^= pow(2, $pos+7);
            } else if ($values[0] == 'away') {
                $newPresence ^= pow(2, $pos+14);
            }
        }
        if (!$days) {
            $pos = date("N")-1;
            if ($values[0] == 'home') {
                $newPresence |= pow(2, $pos);
            } else if ($values[0] == 'office') {
                $newPresence &= ~pow(2, $pos);
            } else if ($values[0] == 'sick') {
                $newPresence ^= pow(2, $pos+7);
            } else if ($values[0] == 'away') {
                $newPresence ^= pow(2, $pos+14);
            }
        }
        return $newPresence;
    }

    /**
     *
     * @return string
     */
    private function people()
    {
        $userRepository = $this->getDoctrine()->getRepository('AppBundle:User');

        $response = "```\n"
                . "+------------+-----------+-----------+-----------+-----------+-----------+\n"
                . "| Person     | Monday    | Tuesday   | Wednesday | Thursday  | Friday    |\n"
                . "+------------+-----------+-----------+-----------+-----------+-----------+\n";
        $users = 0;
        foreach ($userRepository->findBy([], ['name' => 'ASC']) as $user) {
            $users++;
            $response .= "| " . sprintf("%10s", $user->getName()) . " |";
            for ($i = 0; $i < 5; $i++) {
                if (!isset($office[$i])) {
                    $office[$i] = 0;
                }
                if (pow(2, $i+14) & $user->getPresence()) {
                    $response .= "   Away    |";
                } else if (pow(2, $i+7) & $user->getPresence()) {
                    $response .= "   Sick    |";
                } else if (pow(2, $i) & $user->getPresence()) {
                    $response .= "   Home    |";
                } else {
                    $response .= "  Office   |";
                    $office[$i] ++;
                }
            }
            $response .= "\n";
        }
        $response .= "+------------+-----------+-----------+-----------+-----------+-----------+\n";
        $response .= "| Office --> |";
        for ($i = 0; $i < 5; $i++) {
            $response .= " " . sprintf("%8d%%", 100 * $office[$i] / $users) . " |";
        }
        $response .= "\n"
                . "+------------+-----------+-----------+-----------+-----------+-----------+\n"
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
                if (pow(2, $i+14) & $user->getPresence()) {
                    $response .= " - |";
                } else if (pow(2, $i+7) & $user->getPresence()) {
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

}
