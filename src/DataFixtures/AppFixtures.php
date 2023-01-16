<?php

namespace App\DataFixtures;

use App\Entity\Author;
use App\Entity\Book;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
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
        $book->setAuthor($authors[array_rand($authors)]);

        return $book;
    }
}
