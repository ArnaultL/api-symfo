<?php

namespace App\Controller;

use App\Repository\BookRepository;
use App\Entity\Book;
use App\Repository\AuthorRepository;
use Doctrine\ORM\EntityManagerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use App\Service\VersioningService;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Attributes as OA;

class BookController extends AbstractController
{
    /**
     * Cette méthode permet de récupérer l'ensemble des livres.
     *
     *
     * @param BookRepository $bookRepository
     * @param SerializerInterface $serializer
     * @param Request $request
     * @return JsonResponse
     */
    #[Route('api/books', name: 'readBooks', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'Retourne la liste des livres',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: new Model(type: Book::class, groups: ['getBooks']))
        )
    )]
    #[OA\Parameter(
        name: 'page',
        in: 'query',
        description: 'La page que l\'on veut récupérer',
        schema: new OA\Schema(type: 'int')
    )]
    #[OA\Parameter(
        name: 'limit',
        in: 'query',
        description: 'Le nombre d\'éléments que l\'on veut récupérer',
        schema: new OA\Schema(type: 'int')
    )]
    #[OA\Tag(name: 'Books')]
    public function getBooks(BookRepository $bookRepository, SerializerInterface $serializer, Request $request, TagAwareCacheInterface $cachePool): JsonResponse
    {
        $page = $request->get('page', 1);
        $limit = $request->get('limit', 3);
        $idCache = "getBooks-" . $page . "-" . $limit;
        $jsonBooks = $cachePool->get($idCache, function (ItemInterface $item) use ($bookRepository, $page, $limit, $serializer) {
            $item->tag("booksCache");
            $books =  $bookRepository->findAllWithPagination($page, $limit);
            $context = SerializationContext::create()->setGroups(['getBooks']);
            return $serializer->serialize($books, 'json', $context);
        });
        return new JsonResponse($jsonBooks, Response::HTTP_OK, [], true);
    }

    /**
     * Cette méthode permet de récupérer un livre en particulier.
     *
     */
    #[Route('api/books/{id}', name: 'readBook', methods: ['GET'])]
    #[OA\Tag(name: 'Books')]
    public function getDetailBook(Book $book, SerializerInterface $serializer, VersioningService $versioningService): JsonResponse
    {
        $version = $versioningService->getVersion();
        $context = SerializationContext::create()->setGroups(['getBooks']);
        $context->setVersion($version);
        $jsonBook = $serializer->serialize($book, 'json', $context);
        return new JsonResponse($jsonBook, Response::HTTP_OK, [], true);
    }

    /**
     * Cette méthode permet de supprimer un livre.
     *
     */
    #[Route('api/books/{id}', name: 'deleteBook', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN', message: 'Vous n\'avez pas les droits suffisants pour supprimer un livre')]
    #[OA\HeaderParameter(
        name: 'Authorization',
        required: true
    )]
    #[OA\Response(
        response: 204,
        description: 'Supprime un livre'
    )]
    #[OA\Tag(name: 'Books')]
    public function deleteBook(Book $book, EntityManagerInterface $em, TagAwareCacheInterface $cachePool): JsonResponse
    {
        $cachePool->invalidateTags(["booksCache"]);
        $em->remove($book);
        $em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Cette méthode permet d'ajouter un livre
     *
     */
    #[Route('api/books', name: 'createBook', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN', message: 'Vous n\'avez pas les droits suffisants pour créer un livre')]
    #[OA\RequestBody(content: new Model(type: Book::class, groups: ["getBooks"]))]
    #[OA\Response(response: 201, description: 'Crée un livre', content: new Model(type: Book::class, groups: ["getBooks"]))]
    #[OA\Tag(name: 'Books')]
    public function createBook(Request $request, SerializerInterface $serializer, EntityManagerInterface $em,
     UrlGeneratorInterface $urlGenerator, AuthorRepository $authorRepository, ValidatorInterface $validator): JsonResponse
    {
        $book = $serializer->deserialize($request->getContent(), Book::class, 'json');
        $content = $request->toArray();
        $idAuthor = $content['idAuthor'] ?? -1;
        $book->setAuthor($authorRepository->find($idAuthor));
        $errors = $validator->validate($book);
        if ($errors->count() > 0) {
            return new JsonResponse($serializer->serialize($errors, 'json'), JsonResponse::HTTP_BAD_REQUEST,
                [], true
            );
        }
        $em->persist($book);
        $em->flush();

        $context = SerializationContext::create()->setGroups(['getBooks']);
        $jsonBook = $serializer->serialize($book, 'json', $context);

        $location = $urlGenerator->generate('readBook', ['id' => $book->getId()],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        return new JsonResponse($jsonBook, Response::HTTP_CREATED, ['location' => $location], true);
    }

    /**
     * Cette méthode permet de modifier/remplacer un livre.
     *
     */
    #[Route('api/books/{id}', name: 'updateBook', methods: ['PUT'])]
    #[IsGranted('ROLE_ADMIN', message: 'Vous n\'avez pas les droits suffisants pour modifier un livre existant')]
    #[OA\Tag(name: 'Books')]
    public function updateBook(Request $request, SerializerInterface $serializer, Book $currentBook,
        EntityManagerInterface $em, AuthorRepository $authorRepository, ValidatorInterface $validator): JsonResponse
    {
        $replacedBook = $serializer->deserialize(
            $request->getContent(),
            Book::class,
            'json'
        );
        $currentBook->setTitle($replacedBook->getTitle());
        $currentBook->setCoverText($replacedBook->getCoverText());

        $content = $request->toArray();
        $idAuthor = $content['idAuthor'] ?? -1;
        $currentBook->setAuthor($authorRepository->find($idAuthor));
        $errors = $validator->validate($currentBook);
        if ($errors->count() > 0) {
            return new JsonResponse($serializer->serialize($errors, 'json'), JsonResponse::HTTP_BAD_REQUEST,
                [], true
            );
        }
        $em->persist($currentBook);
        $em->flush();
        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }
}
