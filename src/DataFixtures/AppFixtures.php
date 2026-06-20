<?php

namespace App\DataFixtures;

use App\Entity\Konzert;
use App\Entity\Liederliste;
use App\Entity\SongKeyword;
use App\Entity\SongSuggestion;
use App\Entity\Style;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public const MEMBER_USERNAME    = 'testuser';
    public const ADMIN_USERNAME     = 'testadmin';
    public const NOTENWART_USERNAME = 'testnotenwart';
    public const PASSWORD           = 'Test1234!';

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

        $notenwart = new User();
        $notenwart->setUsername(self::NOTENWART_USERNAME);
        $notenwart->setFirstName('Test');
        $notenwart->setLastName('Notenwart');
        $notenwart->setEmail('testnotenwart@example.com');
        $notenwart->setPassword($this->hasher->hashPassword($notenwart, self::PASSWORD));
        $notenwart->setRoles(['ROLE_NOTENWART']);
        $manager->persist($notenwart);

        $style = new Style();
        $style->setName('Gospel');
        $style->setColor('#7c3aed');
        $manager->persist($style);

        // Song in Noten (no Dropbox link)
        $song = new SongKeyword();
        $song->setSongName('Amazing Grace');
        $song->setComposer('John Newton');
        $song->setFolder('Noten');
        $manager->persist($song);

        // Song in Noten with Dropbox link (tests API call flow)
        $songWithDropbox = new SongKeyword();
        $songWithDropbox->setSongName('Ave Maria');
        $songWithDropbox->setComposer('Franz Schubert');
        $songWithDropbox->setFolder('Noten');
        $songWithDropbox->setDropboxlink('/Noten/Ave Maria');
        $manager->persist($songWithDropbox);

        // Song with child movements
        $parentSong = new SongKeyword();
        $parentSong->setSongName('Requiem');
        $parentSong->setComposer('Wolfgang A. Mozart');
        $parentSong->setFolder('Noten');
        $parentSong->setDropboxlink('/Noten/Requiem');
        $manager->persist($parentSong);

        $movement1 = new SongKeyword();
        $movement1->setSongName('I. Introitus');
        $movement1->setFolder('Noten');
        $movement1->setDropboxlink('/Noten/Requiem/Introitus');
        $movement1->setParent($parentSong);
        $movement1->setSortOrder(1);
        $manager->persist($movement1);

        $movement2 = new SongKeyword();
        $movement2->setSongName('II. Kyrie');
        $movement2->setFolder('Noten');
        $movement2->setDropboxlink('/Noten/Requiem/Kyrie');
        $movement2->setParent($parentSong);
        $movement2->setSortOrder(2);
        $manager->persist($movement2);

        // Aktuelle Proben song (no Dropbox)
        $probenSong = new SongKeyword();
        $probenSong->setSongName('Dona Nobis Pacem');
        $probenSong->setComposer('Traditional');
        $probenSong->setFolder('Aktuelle Proben');
        $probenSong->setIsAktuelleProben(true);
        $manager->persist($probenSong);

        // Aktuelle Proben song with Dropbox link
        $probenSongWithDropbox = new SongKeyword();
        $probenSongWithDropbox->setSongName('Halleluja Chorus');
        $probenSongWithDropbox->setComposer('Georg F. Händel');
        $probenSongWithDropbox->setFolder('Aktuelle Proben');
        $probenSongWithDropbox->setIsAktuelleProben(true);
        $probenSongWithDropbox->setAktuelleDropboxlink('/Aktuelle Proben/Halleluja Chorus');
        $manager->persist($probenSongWithDropbox);

        $konzert = new Konzert();
        $konzert->setName('Sommerkonzert 2024');
        $konzert->setDate(new \DateTime('2024-07-15', new \DateTimeZone('Europe/Berlin')));
        $konzert->setLocation('Teutonia Saal');
        $manager->persist($konzert);

        $suggestion = new SongSuggestion();
        $suggestion->setTitle('Hallelujah');
        $suggestion->setArtist('Leonard Cohen');
        $suggestion->setDescription('Ein Klassiker für den Chor');
        $suggestion->setSubmittedBy($member);
        $manager->persist($suggestion);

        $manager->flush();

        // A Liederliste for the Notenwart read-only flow. Built after the flush so
        // the referenced song already has a database ID.
        $liederliste = new Liederliste();
        $liederliste->setName('Notenwart Liste');
        $liederliste->setTitle('Programm Notenwart');
        $liederliste->setItems([
            [
                'type'     => 'song',
                'songId'   => $song->getId(),
                'composer' => $song->getComposer(),
                'title'    => $song->getSongName(),
                'duration' => '3:30',
                'note'     => '',
            ],
            [
                'type'     => 'custom',
                'songId'   => null,
                'composer' => 'Anonymus',
                'title'    => 'Notenwart Test Zugabe',
                'duration' => '2:00',
                'note'     => '',
            ],
        ]);
        $manager->persist($liederliste);

        $manager->flush();
    }
}
