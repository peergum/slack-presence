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
                case 'vacation':
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
                    $response = "Try `[home|office]: [mon|tue|wed|thu|fri]..`\n"
                            . "Or `people` (`compact` on cell)";
                    break;
            }
        } else {
            $response = "I didn't get it...";
            return new Response(json_encode([
                        'text' => $response,
            ]));
        }

        return new Response(json_encode([
                    'text' => $response,
        ]));
    }

    private function setDays($presence, $values)
    {
        $newPresence = $presence;
        for ($i = 1; $i < count($values); $i++) {
            $pos = array_search(substr($values[$i], 0, 3), ['mon', 'tue', 'wed', 'thu', 'fri']);
            if ($pos === false) {
                continue;
            }
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
     * @return string
     */
    private function people()
    {
        $userRepository = $this->getDoctrine()->getRepository('AppBundle:User');

        $response = "```\n"
                . "|------------|-----------|-----------|-----------|-----------|-----------|\n"
                . "| Person     | Monday    | Tuesday   | Wednesday | Thursday  | Friday    |\n"
                . "|------------|-----------|-----------|-----------|-----------|-----------|\n";
        $users = 0;
        foreach ($userRepository->findBy([], ['name' => 'ASC']) as $user) {
            $users++;
            $response .= "| " . sprintf("%10s", $user->getName()) . " |";
            for ($i = 0; $i < 5; $i++) {
                if (!isset($office[$i])) {
                    $office[$i] = 0;
                }
                if (pow(2, $i) & $user->getPresence()) {
                    $response .= "   Home    |";
                } else {
                    $response .= "  Office   |";
                    $office[$i]++;
                }
            }
            $response .= "\n";
        }
        $response .= "|------------|-----------|-----------|-----------|-----------|-----------|\n";
        $response .= "| Office --> |";
        for ($i = 0; $i < 5; $i++) {
            $response .= " " . sprintf("%8d%%", 100 * $office[$i] / $users) . " |";
        }
        $response .= "\n"
            . "|------------|-----------|-----------|-----------|-----------|-----------|\n"
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
                . "| Person     | M | T | W | T | F |\n"
                . "|------------|---|---|---|---|---|\n";
        foreach ($userRepository->findBy([], ['name' => 'ASC']) as $user) {
            $response .= "| " . sprintf("%10s", $user->getName()) . " |";
            for ($i = 0; $i < 5; $i++) {
                if (pow(2, $i) & $user->getPresence()) {
                    $response .= " H |";
                } else {
                    $response .= " O |";
                }
            }
            $response .= "\n";
        }
        $response .= "```\n";

        return $response;
    }

}
