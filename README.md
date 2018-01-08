# HQ Answers

Check it out live here: http://www.zakwegweiser.com/hq.html

Feel free to fork this project!

## About

My HQ answerer is a PHP script triggered by a screenshot upload. You take a picture of a multiple choice trivia question (in this case three answer choices), and my answerer uses Optical Character Recognition (OCR) to decipher the text and then figures out the most relevant answer. 

Warning: It is by no means perfect! But is probably better than you on average ;)

## Getting Started

1) download PHP script and set it up on a localhost or webserver
2) set up google's vision api locally, set variables to your auth file etc...
3) plug in your phone to your computer and open quicktime. go to file->new movie, then hit the arrow next to the record button and select your phone. 
4) open automator and allow run screenshot command and a run shell script command.
5) In the shell script run:
```
cd path/to/automator/screenshot
curl -F "image=@./screenshot_name.png" 'http://www.url.com/hq.php'
```

## Usage

During a game, mirror your phone to your mac. 

Run the Automator script and watch it work its magic

Enjoy!

## Disclaimers

This script is NOT to be used during a live game. It's purpose is to show how programming can be fun, and to teach (that's why the code is public!). It's also meant to make HQ work a little harder to make their amazing game even harder to crack!

## Questions? Get in Touch

me@zakwegweiser.com
