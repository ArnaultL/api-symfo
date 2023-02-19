<?php

namespace App\DataFixtures;

use App\Entity\Author;
use App\Entity\Book;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    private $userPasswordHasher;

    public function __construct(UserPasswordHasherInterface $userPasswordHasher)
    {
        $this->userPasswordHasher = $userPasswordHasher;
    }

    public function load(ObjectManager $manager): void
    {
        //normal user
        $user = new User();
        $user->setEmail("user@bookapi.com");
        $user->setRoles(["ROLE_USER"]);
        $user->setPassword($this->userPasswordHasher->hashPassword($user, "password"));
        $manager->persist($user);

        //admin user
        $userAdmin = new User();
        $userAdmin->setEmail("admin@bookapi.com");
        $userAdmin->setRoles(["ROLE_ADMIN"]);
        $userAdmin->setPassword($this->userPasswordHasher->hashPassword($userAdmin, "password"));
        $manager->persist($userAdmin);


        $authors = [];
        for ($i = 0; $i < 20; $i++) {
            $author = $this->makeAuthor($i);
            $manager->persist($author);
            $authors[] = $author;

            $book = $this->makeBook($i, $authors);
            $manager->persist($book);
        }

        $manager->flush();
    }

    private function makeAuthor(int $i): Author
    {
        $author = new Author();
        $author->setFirstName("Prénom " . $i);
        $author->setLastName("Nom " . $i);
        
        return $author;
    }

    private function makeBook(int $i, $authors): Book
    {
        $book = new Book();
        $book->setTitle("Titre " . $i);
        $book->setCoverText("Quatrième de couverture numéro " . $i);
        $book->setComment("Commentaire du bibliothécaire " . $i);
        $book->setAuthor($authors[array_rand($authors)]);

        return $book;
    }
}
