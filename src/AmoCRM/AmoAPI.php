<?php
/**
 * Класс AmoAPI. amoCRM REST API wrapper
 *
 * @author    andrey-tech
 * @copyright 2019-2020 andrey-tech
 * @see https://github.com/andrey-tech/amocrm-api-php
 * @license   MIT
 *
 * @version 2.3.1
 *
 * v1.0.0 (24.04.2019) Начальный релиз
 * v1.1.0 (02.06.2019) Добавлены новые параметры, рефракторинг.
 * v1.2.0 (19.08.2019) Добавлен метод deleteObjects()
 * v1.2.1 (19.02.2020) Удален метод deleteObjects()
 * v2.0.0 (06.04.2020) Добавлена авторизация по протоколу OAuth 2.0.
 *                     Добавлены трейты AmoAPIAuth, AmoAPIOAuth2
 * v2.1.0 (10.05.2020) Добавлена проверка ответа сервера в метод saveObjects()
 * v2.2.0 (16.05.2020) Добавлен метод getItems(). Добавлен параметр $returnResponses в метод saveObjects()
 * v2.3.0 (22.05.2020) Добавлен метод deleteObjects() для удаления списков и их элементов
 * v2.3.1 (14.07.2020) Изменен порядок параметров $subdomain и $returnResponse в методах
 *
 */

declare(strict_types = 1);

namespace AmoCRM;

class AmoAPI
{
    // Трейт, формирующий GET/POST запросы к amoCRM
    use AmoAPIRequest;

    // Трейт методов для получения информации об аккаунте
    use AmoAPIGetAccount;

    // Трейт методов для получения сущностей
    use AmoAPIGetEntities;

    // Трейт методов для получения всех сущностей
    use AmoAPIGetAllEntities;

    // Трейт методов для авторизации по API-ключам пользователя
    use AmoAPIAuth;

    // Трейт методов для авторизации по протоколу OAuth 2.0
    use AmoAPIOAuth2;

    // Трейт методов для добавления и удаления webhooks
    use AmoAPIWebhooks;

    /**
     * Возращает массив параметров сущностей из ответа сервера amoCRM
     * @param array|null $response Ответ сервера
     * @return array|null
     */
    public static function getItems($response)
    {
        return $response['_embedded']['items'] ?? null;
    }

    /**
     * Сохраняет (добавляет или обновляет) объекты AmoObject
     * @param array $amoObjects Массив объектов
     * @param bool $returnResponses Возвращать массив ответов сервера amoCRM вместо массива параметров сущностей
     * @param string $subdomain Поддомен amoCRM
     * @return array
     */
    // ------------------------------------------------------------------------
    public static function saveObjects($amoObjects, bool $returnResponses = false, $subdomain = null) :array
    {
        if (! is_array($amoObjects)) {
            $amoObjects = [ $amoObjects ];
        }
        
        $parameters = [];
        foreach ($amoObjects as $object) {
            if (isset($object->id)) {
                $parameters[$object::URL]['update'][] = $object->getParams();
            } else {
                $parameters[$object::URL]['add'][] = $object->getParams();
            }
        }

        $responses = [];
        foreach ($parameters as $url => $params) {
            $response = AmoAPI::request($url, 'POST', $params, $subdomain);
            if (empty($response)) {
                throw new AmoAPIException(
                    "Не удалось пакетно добавить/обновить сущности (пустой ответ) по запросу {$url}: " . print_r($params, true)
                );
            }
            $responses[] = $response;
        }

        if (! $returnResponses) {
            $items = [];
            foreach ($responses as $response) {
                $items = array_merge($items, self::getItems($response));
            }
            return $items;
        }
        
        return $responses;
    }

    /**
     * Удаляет объекты AmoObject (списки или элементы списков)
     * @param array $amoObjects Массив объектов
     * @param bool $returnResponses Возвращать массив ответов сервера amoCRM вместо массива параметров сущностей
     * @param string $subdomain Поддомен amoCRM
     * @return array
     */
    public static function deleteObjects($amoObjects, bool $returnResponses = false, $subdomain = null) :array
    {
        if (! is_array($amoObjects)) {
            $amoObjects = [ $amoObjects ];
        }
        
        $parameters = [];
        foreach ($amoObjects as $object) {
            $params = $object->getParams();
            $id = $params['id'] ?? null;
            if (! $id) {
                throw new AmoAPIException("Для удаления сущности требуется свойство id: " . print_r($params, true));
            }
            $parameters[$object::URL]['delete'][] = $id;
        }

        $responses = [];
        foreach ($parameters as $url => $params) {
            $response = AmoAPI::request($url, 'POST', $params, $subdomain);
            if (empty($response)) {
                throw new AmoAPIException(
                    "Не удалось пакетно удаилить сущности (пустой ответ) по запросу {$url}: " . print_r($params, true)
                );
            }
            $responses[] = $response;
        }

        if (! $returnResponses) {
            $items = [];
            foreach ($responses as $response) {
                $items = array_merge($items, self::getItems($response));
            }
            return $items;
        }
        
        return $responses;
    }
}
