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

