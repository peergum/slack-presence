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

        $response = "All set, " . $args['user_name'];
        if (strpos($args['text'], 'home:') === 0) {
            $mode = 'home';
        } else if (strpos($args['text'], 'office:') === 0) {
            $mode = 'office';
        } else if (strpos($args['text'], 'sick') === 0) {
            $mode = 'sick';
        } else if (strpos($args['text'], 'people') === 0) {
            $response = $this->people();
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

    /**
     *
     * @return string
     */
    private function people()
    {
        $userRepository = $this->getDoctrine()->getRepository('AppBundle:User');

        $response = "```\n"
                . "| Person     | Mon | Tue | Wed | Thu | Fri |\n"
                . "|------------|-----|-----|-----|-----|-----|\n";
        foreach ($userRepository->findBy([], ['name' => 'ASC']) as $user) {
            $response .= "| " . sprintf("%10s", $user->getName()) . " | ";
            for ($i = 0; $i < 5; $i++) {
                if (pow(2, $i) & $user->getPresence()) {
                    $response .= "  H  |";
                } else {
                    $response .= "  *  |";
                }
            }
            $response .= "\n";
        }
        $response .= "```\n";

        return $response;
    }

}
