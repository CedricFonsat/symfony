<?php

namespace App\Controller;

use Twig\Environment;
use App\Entity\Comment;
use App\Entity\Conference;
use App\Form\CommentFormType;
use App\Repository\CommentRepository;
use App\Repository\ConferenceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

class ConferenceController extends AbstractController
{

    public function __construct(private readonly EntityManagerInterface $entityManager) { }

    #[Route('/', name: 'homepage')]
    public function index(Environment $twig, ConferenceRepository $conferenceRepository): Response
    {
        // dd($request);

        // return new Response(<<<EOF
        //            <html>
        //            <body><img src="/images/under-construction.gif" /></body>
        //            </html>
        //        EOF);


               return new Response($twig->render('conference/index.html.twig', [
                           'conferences' => $conferenceRepository->findAll(),
                      ]));
    }

    
    #[Route('/conference/{slug}', name: 'conference')]
    public function show(
                Request $request,
                Conference $conference,
                Environment $twig,
                CommentRepository $commentRepository,
                #[Autowire('%photo_dir%')] string $photoDir,
            ): Response {
    
        $offset = max(0, $request->query->getInt('offset', 0));
        $paginator = $commentRepository->getCommentPaginator($conference, $offset);

        $comment = new Comment();
        $form = $this->createForm(CommentFormType::class, $comment);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $comment->setConference($conference);

                    if ($photo = $form['photo']->getData()) {
                                $filename = bin2hex(random_bytes(6)).'.'.$photo->guessExtension();
                                try {
                                    $photo->move($photoDir, $filename);
                                } catch (FileException $e) {
                                    // unable to upload the photo, give up
                            }
                                $comment->setPhotoFilename($filename);
                        }
                

            $this->entityManager->persist($comment);
            $this->entityManager->flush();
        

            return $this->redirectToRoute('conference', ['slug' => $conference->getSlug()]);
        }

        return new Response($twig->render('conference/show.html.twig', [
            'conference' => $conference,
            'comments' => $commentRepository->findBy(['conference' => $conference], ['createdAt' => 'DESC']),
            'comments' => $paginator,
            'previous' => $offset - CommentRepository::PAGINATOR_PER_PAGE,
            'next' => min(count($paginator), $offset + CommentRepository::PAGINATOR_PER_PAGE),
            'comment_form' => $form->createView(),
        ]));
    }
}
