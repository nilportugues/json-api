 JSON API Transformers 
=========================================

[![Join the chat at https://gitter.im/nilportugues/json-api](https://badges.gitter.im/Join%20Chat.svg)](https://gitter.im/nilportugues/json-api?utm_source=badge&utm_medium=badge&utm_campaign=pr-badge&utm_content=badge)

[![Build Status](https://travis-ci.org/nilportugues/json-api.svg)](https://travis-ci.org/nilportugues/json-api) [![Coverage Status](https://coveralls.io/repos/nilportugues/json-api/badge.svg?branch=master)](https://coveralls.io/r/nilportugues/json-api?branch=master) [![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/nilportugues/json-api/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/nilportugues/json-api/?branch=master) [![SensioLabsInsight](https://insight.sensiolabs.com/projects/e39e4c0e-a402-495b-a763-6e0482e2083d/mini.png)](https://insight.sensiolabs.com/projects/e39e4c0e-a402-495b-a763-6e0482e2083d) [![Latest Stable Version](https://poser.pugx.org/nilportugues/json-api/v/stable)](https://packagist.org/packages/nilportugues/json-api) [![Total Downloads](https://poser.pugx.org/nilportugues/json-api/downloads)](https://packagist.org/packages/nilportugues/json-api) [![License](https://poser.pugx.org/nilportugues/json-api/license)](https://packagist.org/packages/nilportugues/json-api) 

Serializer transformers outputting valid API responses in JSON, JSON API and HAL+JSON API formats.

## Installation
The recommended way to install the  JSON API Transformers is through [Composer](http://getcomposer.org). Run the following command to install it:

```sh
php composer.phar require nilportugues/json-api
```

## Usage
Given the following piece of code, defining a Blog Post and some Comments, we'll examine how each transformer works:

```php
$post = new Post(
  new PostId(9),
  'Hello World',
  'Your first post',
  new User(
      new UserId(1),
      'Post Author'
  ),
  [
      new Comment(
          new CommentId(1000),
          'Have no fear, sers, your king is safe.',
          new User(new UserId(2), 'Barristan Selmy'),
          [
              'created_at' => (new DateTime('2015/07/18 12:13:00'))->format('c'),
              'accepted_at' => (new DateTime('2015/07/19 00:00:00'))->format('c'),
          ]
      ),
  ]
);
```



### JSON 
Given a PHP object, the JSON transformer will transform it to a valid JSON representation. It will preserve the data structure given by the properties of the classes and arrays used by the PHP Object.

**Code:**

```php
use NilPortugues\Serializer\Serializer;
use NilPortugues\Api\Transformer\Json\JsonTransformer;

$transformer = new JsonTransformer();
$serializer = new Serializer($transformer);

echo $serializer->serialize($post);
```

**Output:**

```json
{
    "postId": 9,
    "title": "Hello World",
    "content": "Your first post",
    "author": {
        "userId": 1,
        "name": "Post Author"
    },
    "comments": [
        {
            "commentId": 1000,
            "dates": {
                "created_at": "2015-07-18T13:34:55+02:00",
                "accepted_at": "2015-07-18T14:09:55+02:00"
            },
            "comment": "Have no fear, sers, your king is safe.",
            "user": {
                "userId": 2,
                "name": "Barristan Selmy"
            }
        }
    ]
}
```


### JSON API
Given a PHP Object, and a series of mappings, the JSON API transformer will represent the given data following the `http://jsonapi.org` specification.

**Code:**

```php
//Create the mappings for each class involved.
$postMapping = new Mapping(
   Post::class, 
   'http://example.com/posts/{postId}',
   ['postId']
);

$postIdMapping = new Mapping(
   PostId::class, 
   'http://example.com/posts/{postId}', 
   ['postId']
);

$userMapping = new Mapping(
   User::class, 
   'http://example.com/users/{userId}', 
   ['userId']
);

$userIdMapping = new Mapping(
   UserId::class,
   'http://example.com/users/{userId}', 
   ['userId']
);

$commentMapping = new Mapping(
   Comment::class, 
   'http://example.com/comments/{commentId}', 
   ['commentId']
);
$commentIdMapping = new Mapping(
   CommentId::class,
   'http://example.com/comments/{commentId}',
   ['commentId']
);

//Build the Mapping array
$mappings = [
   $postMapping->getClassName() => $postMapping,
   $postIdMapping->getClassName() => $postIdMapping,
   $userMapping->getClassName() => $userMapping,
   $userIdMapping->getClassName() => $userIdMapping,
   $commentMapping->getClassName() => $commentMapping,
   $commentIdMapping->getClassName() => $commentIdMapping,
];

//Build the JsonApi Transformer and set additional fields.
$transformer = new JsonApiTransformer($mappings);
$transformer->setApiVersion('1.0');
$transformer->setSelfUrl('http://example.com/posts/1');
$transformer->setFirstUrl('http://example.com/posts/1');
$transformer->setNextUrl('http://example.com/posts/2');
$transformer->addMeta(
   'author', 
   [
      ['name' => 'Nil Portugués Calderó', 'email' => 'contact@nilportugues.com']
   ]
);

//Output transformation
$serializer = new Serializer($transformer);
echo $serializer->serialize($post);
```

**Output:**

```json
{
    "data": {
        "type": "post",
        "id": "9",
        "attributes": {
            "title": "Hello World",
            "content": "Your first post"
        },
        "links": {
            "self": "http://example.com/posts/9"
        },
        "relationships": {
            "author": {
                "links": {
                    "self": "http://example.com/users/1"
                },
                "data": {
                    "type": "user",
                    "id": "1"
                }
            }
        }
    },
    "included": [
        {
            "type": "user",
            "id": "1",
            "attributes": {
                "name": "Post Author"
            },
            "links": {
                "self": "http://example.com/users/1"
            },
            "relationships": [

            ]
        },
        {
            "type": "user",
            "id": "2",
            "attributes": {
                "name": "Barristan Selmy"
            },
            "links": {
                "self": "http://example.com/users/2"
            },
            "relationships": [

            ]
        },
        {
            "type": "comment",
            "id": "1000",
            "attributes": {
                "dates": {
                    "created_at": "2015-07-18T13:43:48+02:00",
                    "accepted_at": "2015-07-18T14:18:48+02:00"
                },
                "comment": "Have no fear, sers, your king is safe."
            },
            "links": {
                "self": "http://example.com/comments/1000"
            },
            "relationships": {
                "user": {
                    "links": {
                        "self": "http://example.com/users/2"
                    },
                    "data": {
                        "user": {
                            "type": "user",
                            "id": "2"
                        }
                    }
                }
            }
        }
    ],
    "links": {
        "self": "http://example.com/posts/1",
        "next": "http://example.com/posts/2"
    },
    "meta": {
        "author": [
            {
                "name": "Nil Portugués Calderó",
                "email": "contact@nilportugues.com"
            }
        ]
    },
    "jsonapi": {
        "version": "1.0"
    }
}
```

### HAL+JSON
Given a PHP Object, and a series of mappings, the HAL+JSON API transformer will represent the given data following the `http://stateless.co/hal_specification.html` specification.


## Quality Code
Testing has been done using PHPUnit and [Travis-CI](https://travis-ci.org). All code has been tested to be compatible from PHP 5.5 and [HHVM](http://hhvm.com/).

To run the test suite, you need [Composer](http://getcomposer.org):

```bash
    php composer.phar install
    php bin/phpunit
```


## Author
Nil Portugués Calderó

 - <contact@nilportugues.com>
 - [http://nilportugues.com](http://nilportugues.com)
