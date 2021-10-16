##AccessAttempts - Попытка доступа

В коде есть места, в которых нужно ограничить частые запросы, что бы избежать цикличный подбор данных или заблокировать запросы парсинг процессов.

- **Via Composer**

```bash
composer require alexgrizzled/access-attempts
```

####Пример использования:

```yaml
# config/services.yaml
parameters:
    login.ip.attempt.max: 3
    login.ip.attempt.interval: 300
    
    login.email.attempt.max: 5
    login.email.attempt.interval: 600

services:
    AlexGrizzled\Service\AccessAttempts: ~
```

```php
<?php

namespace App\Controller;

use AlexGrizzled\Service\AccessAttempts;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class SecurityController extends AbstractController
{
    /**
     * @Route("/login", name="login")
     */
    public function login(Request $request, AccessAttempts $accessAttempts): Response
    {
        // ...
        
        $ip = $request->getClientIp();
        $email = $request->request->get('email');
        
        if (false === $accessAttempts->has('login.ip', $ip) || false === $accessAttempts->has('login.email', $email)) {
            throw new Exception('Ну очень много попыток.');
        }
        
        // ...
    }
}
```

Если не указать параметры в config/services.yaml, то по умолчанию будут применены следующие характеристики:

(ResourceName).attempt.max - Максимально количество попыток: 3 раза

(ResourceName).attempt.interval - Интервал учёта попыток: 600 секунд

####Примечание
Выше изложенный пример не претендует на copy past в вашем коде, так как в symfony за обработку данных в login отвечает
клас унаследованный от Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator

###Ниже приведённый пример показывает как заблокировать злостных нарушителей на долгий срок

```yaml
# config/services.yaml
parameters:
    login.ip.attempt.max: 3
    login.ip.attempt.interval: 300
    
    login.day.attempt.max: 6
    login.day.attempt.interval: 86400

services:
    AlexGrizzled\Service\AccessAttempts: ~
```

```php
<?php

namespace App\Controller;

use AlexGrizzled\Service\AccessAttempts;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class SecurityController extends AbstractController
{
    /**
     * @Route("/login", name="login")
     */
    public function login(Request $request, AccessAttempts $accessAttempts): Response
    {
        // ...
        
        $ip = $request->getClientIp();
        
        // Проверка доступа без суммирования попытки
        if (false === $accessAttempts->has('login.day', $ip, false)) {
            // Бан на 24 часа
            // В этом месте можно и в фаервол правило записать, но это другая история ;)
            throw new Exception('Ну очень много попыток.');
        }
        
        if (false === $accessAttempts->has('login.ip', $ip)) {
            // Бан на 5 минут
            throw new Exception('Попозже попробуйте повторить');
        }
        
        // Суммируем попытку
        $accessAttempts->has('login.day', $ip)
        
        // ...
    }
}
```
