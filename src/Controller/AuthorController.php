<?php

namespace App\Controller;

use App\Repository\AuthorRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use App\Entity\Author;

class AuthorController extends AbstractController
{
    #[Route('api/authors', name: 'authorAll', methods: ['GET'])]
    public function getAuthors(AuthorRepository $authorRepository, SerializerInterface $serializer): JsonResponse
    {
        $authors = $authorRepository->findAll();
        $jsonAuthors = $serializer->serialize($authors, 'json', ['groups' => ['getAuthors']]);
        return new JsonResponse($jsonAuthors, Response::HTTP_OK, [], true);
    }

    #[Route('api/authors/{id}', name: 'authorDetails', methods: ['GET'])]
    public function getAuthor(Author $author, SerializerInterface $serializer): JsonResponse
    {
        
        $jsonAuthor = $serializer->serialize($author, 'json', ['groups' => 'getAuthors']);
        return new JsonResponse($jsonAuthor, Response::HTTP_OK, [], true);
    }
}
