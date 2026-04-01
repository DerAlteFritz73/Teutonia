<?php

namespace App\DataFixtures;

use App\Entity\Konzert;
use App\Entity\SongKeyword;
use App\Entity\SongSuggestion;
use App\Entity\Style;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public const MEMBER_USERNAME = 'testuser';
    public const ADMIN_USERNAME  = 'testadmin';
    public const PASSWORD        = 'Test1234!';

    public function __construct(private UserPasswordHasherInterface $hasher) {}

    public function load(ObjectManager $manager): void
    {
        $member = new User();
        $member->setUsername(self::MEMBER_USERNAME);
        $member->setFirstName('Test');
        $member->setLastName('User');
        $member->setEmail('testuser@example.com');
        $member->setPassword($this->hasher->hashPassword($member, self::PASSWORD));
        $manager->persist($member);

        $admin = new User();
        $admin->setUsername(self::ADMIN_USERNAME);
        $admin->setFirstName('Test');
        $admin->setLastName('Admin');
        $admin->setEmail('testadmin@example.com');
        $admin->setPassword($this->hasher->hashPassword($admin, self::PASSWORD));
        $admin->setRoles(['ROLE_ADMIN']);
        $manager->persist($admin);

        $style = new Style();
        $style->setName('Gospel');
        $style->setColor('#7c3aed');
        $manager->persist($style);

        $song = new SongKeyword();
        $song->setSongName('Amazing Grace');
        $song->setComposer('John Newton');
        $song->setFolder('Noten');
        $manager->persist($song);

        $konzert = new Konzert();
        $konzert->setName('Sommerkonzert 2024');
        $konzert->setDate(new \DateTime('2024-07-15'));
        $konzert->setLocation('Teutonia Saal');
        $manager->persist($konzert);

        $suggestion = new SongSuggestion();
        $suggestion->setTitle('Hallelujah');
        $suggestion->setArtist('Leonard Cohen');
        $suggestion->setDescription('Ein Klassiker für den Chor');
        $suggestion->setSubmittedBy($member);
        $manager->persist($suggestion);

        $manager->flush();
    }
}
