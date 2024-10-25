# Procédure de création d'un back end d'authentification

```bash
$ symfony new test_auth
$ cd test_auth
$ composer require symfony/security-bundle
$ composer require symfony/maker-bundle --dev
$ composer require orm
$ cp .env .env.local  # il faut déclarer la connexion dans le fichier .env
```

## Accès à l'application

Grâce à Laragon, l'URL est `http://test_auth.local/`.
Pour permettre à toutes les pages d'être servies par le serveur apache de Laragon,
il faut installer le package suivant :

```bash
$ composer require symfony/apache-pack 
```

## Création d'une BDD et d'un user dédié depuis phpstorm

Il faut établir la connexion avec l'utilisateur postgres.
Ensuite, clic droit sur postgres > New > Query console :

```sql
create user user_name WITH PASSWORD 'my_password'
    superuser
    createdb;
```

Ensuite, exécution de la commande doctrine et création de la table User :

```bash
$ php bin/console d:d:c
$ php bin/console make:user
$ php bin/console make:migration
$ php bin/console d:m:m -n
```
Pour ajouter un user dans la table créée, il faut `hasher` son mot de passe,
avec la commande :

```bash
$ php bin/console security:hash-password
```

Ensuite, insérer les données en base, en veillant à fournir un tableau json pour le role :

```json
["ROLE_USER"]
```

## Authentification JWT et génération des clés

```bash
$ composer require lexik/jwt-authentication-bundle
$ php bin/console lexik:jwt:generate-keypair
```
Il faut ensuite configurer :

```yaml
# config/packages/security.yaml
        login:
            pattern: ^/api/login
            stateless: true
            json_login:
                check_path: /api/login_check
                success_handler: lexik_jwt_authentication.handler.authentication_success
                failure_handler: lexik_jwt_authentication.handler.authentication_failure

        api:
            pattern:   ^/api
            stateless: true
            jwt: ~
            
    access_control:
        - { path: ^/api/login, roles: PUBLIC_ACCESS }
        - { path: ^/api,       roles: IS_AUTHENTICATED_FULLY }

# config/routes.yaml
api_login_check:
    path: /api/login_check
```

## Récupérer le token

Ouvrir Postman et appeler la route `http://test_auth/api/login_ckeck` en POST.
Ajouter le header `Content-Type` avec pour valeur `application/json`.
Dans le body, en raw, fournir la chaîne json suivante :

```json
{
"username": "username",
"password": "password"
}
```

>NOTE : La clé username est requise, bien que sa valeur puisse être différente (email par ex.).

En réponse, on reçoit le token, qu'il faudra alors enregistrer et fournir dans chaque requête.

>NOTE : Pour tester l'envoi du token depuis Postman, 
> il doit être fourni dans le header Authorization avec comme valeur Bearer <token>,
> sans guillemets entre Bearer et le token.

## Configuration des CORS pour autoriser mon front Vue.js
Pour faciliter la gestion des CORS, j'installe nelmio :

```bash
$ composer require nelmio/cors-bundle
```

Ensuite, je configure le fichier config/packages/nelmio_cors.yaml :

```yaml
# config/packages/nelmio_cors.yaml
nelmio_cors:
    defaults:
        allow_origin: ['%env(CORS_ALLOW_ORIGIN)%']
        allow_headers: ['Content-Type', 'Authorization']
        allow_methods: ['GET', 'POST', 'OPTIONS', 'PUT', 'DELETE']
        max_age: 3600
    paths:
        '^/api/':
            allow_origin: ['%env(CORS_ALLOW_ORIGIN)%']
            allow_headers: ['Content-Type', 'Authorization']
            allow_methods: ['GET', 'POST', 'OPTIONS', 'PUT', 'DELETE']
            max_age: 3600
```

## Transmettre les Positions au front

Il faut convertir les positions du format objet PHP vers JSON au moyen du serializer de Symfony :

``
$ composer require symfony/serializer
``

Pour intégrer les relations, tout en évitant les problèmes de circularité,
je déclare des groupes dans les entités concernées avec l'attribut suivant :

```php
#[Groups(["position_read"])]
```

⚠ Le groupe doit être assigné aux propriétés dans les classes liées.
Par exemple, la propriété `buyLimit` de l'entité **LasHigh** depuis la propriété `LastHigh` de l'entité **Position**)

Côté front, pour afficher la propriété buyLimit de l'entité LastHigh contenu dans l'objet JSON position,
il faut faire :

```js
{{ position.buyLimit.buyLimit }} // la valeur de la seconde buyLimit est celle de la clé buyLimit du tableau position
```

## Formulaire Register

```bash
$ composer require form validator
$ php bin/console make:controller Registration
```

