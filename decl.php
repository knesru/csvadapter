<?php

public function getFilteredList($params, $limit = 50, $offset = 0, $order = 'desc', $sort = 'r_client_id')
    {
        if (!$limit || $limit < 1) {
            $limit = 50;
        }

        if (!$offset || $offset < 0) {
            $offset = 0;
        }

        if (!$order) {
            $order = 'desc';
        }

        if (!$sort) {
            $sort = 'r_client_id';
        }

        $fields = array(
            "clientId" => 'cl.r_client_id as "clientId"',
            "firstName" => 'cl.first_name as "firstName"',
            "lastName" => 'cl.last_name as "lastName"',
            "middleName" => 'cl.middle_name as "middleName"',
            "passportNo" => 'cl.passport_no as "passportNo"',
            "mobilePhone" => 'cl.mobile_phone as "mobilePhone"',
            "homePhone" => 'cl.home_phone as "homePhone"',
            "workplacePhone" => 'cl.workplace_phone as "workplacePhone"',
            "emailAddress" => 'cl.email_address as "emailAddress"',
            "loanId" => 'l.r_loan_id as "loanId"',
            "loanNo" => 'l.loan_no as "loanNo"',
            "loanStatus" => 'l.status as "loanStatus"',
            "installmentLoanProduct" => 'a.product as "installmentLoanProduct"',
            "applicationId" => 'a.r_application_id as "applicationId"',
            "clientBirthDate" => 'cl.dob as "clientBirthDate"'
        );
        $where = array('cl.duplicate_id IS NULL');
        $execute = array();

        if (isset($params['middle_name'])) {
            $where[] = 'cl.middle_name = :middle_name';
            $execute['middle_name'] = $params['middle_name'];
        }
        if (isset($params['passport_no'])) {
            $where[] = 'cl.passport_no = :passport_no';
            $execute['passport_no'] = $params['passport_no'];
        }
        if (!empty($params['r_client_id'])) {
            $ors = array();
            $rClientIds = is_array($params['r_client_id']) ? $params['r_client_id'] : array($params['r_client_id']);

            foreach ($rClientIds as $no => $id) {
                $ors[] = 'cl.r_client_id = :r_client_id_' . $no;
                $execute['r_client_id_' . $no] = $id;
            }

            $where[] = '(' . implode(' OR ', $ors) . ')';
        }
        if (isset($params['client_no'])) {
            $where[] = 'cl.client_no = :client_no';
            $execute['client_no'] = $params['client_no'];
        }
        if (isset($params['first_name'])) {
            $where[] = 'cl.first_name ILIKE :first_name';
            $execute['first_name'] = $params['first_name'].'%';
        }
        if (isset($params['last_name'])) {
            $where[] = 'cl.last_name ILIKE :last_name';
            $execute['last_name'] = $params['last_name'].'%';
        }
        if (!empty($params['email_address'])) {
            $ors = array();
            $emailAddresses = is_array($params['email_address']) ? $params['email_address'] : array($params['email_address']);

            foreach ($emailAddresses as $no => $email) {
                $execute['email_'.$no] = (string) $email;
                $ors[] = 'cl.email_address ILIKE :email_'.$no;
            }

            $where[] = '('.implode(' OR ', $ors).')';
        }
        if (isset($params['r_application_id'])) {
            $execute['r_application_id'] = $params['r_application_id'];
            $where[] = 'cl.r_client_id IN (SELECT r_client_id FROM r_application WHERE r_application_id = :r_application_id)';
        }
        if (isset($params['r_loan_id'])) {
            $execute['r_loan_id'] = $params['r_loan_id'];
            $where[] = 'cl.r_client_id IN (SELECT r_client_id FROM r_loan WHERE r_loan_id = :r_loan_id)';
        }
        if (isset($params['loan_no'])) {
            $execute['loan_no'] = $params['loan_no'];
            $where[] = 'cl.r_client_id IN (SELECT r_client_id FROM r_loan WHERE loan_no = :loan_no)';
        }
        if (isset($params['status'])) {
            $execute['status'] = $params['status'];
            $where[] = 'cl.r_client_id IN (SELECT r_client_id FROM r_loan WHERE status = :status)';
        }
        if (isset($params['product'])) {
            $execute['product'] = $params['product'];
            $where[] = 'cl.r_client_id IN (SELECT r_client_id FROM r_application WHERE product = :product)';
        }
        if (!empty($params['phone'])) {
            $ors = array();
            $phones = is_array($params['phone']) ? $params['phone'] : array($params['phone']);

            foreach ($phones as $no => $phone) {
                $execute['phone_'.$no] = (string) $phone;
                $ors[] = 'cl.home_phone = :phone_'.$no;
                $ors[] = 'cl.mobile_phone = :phone_'.$no;
                $ors[] = 'cl.workplace_phone = :phone_'.$no;
                $ors[] = 'cl.contact_person_phone = :phone_'.$no;
                $ors[] = 'cl.r_client_id IN (SELECT r_client_id FROM dms_contact WHERE dms_contact_type_id = 3 AND value = :phone_'.$no.')';
            }

            $where[] = '('.implode(' OR ', $ors).')';
        }
        if (isset($params['dob'])) {
            $where[] = 'cl.dob = :dob::date';
            $execute['dob'] = $params['dob'];
        }

        $query = "WITH base_clients AS (\n"
            . "SELECT * FROM $this->tableName as cl\n"
            . "WHERE " . implode(' AND ', $where) . "\n"
            . "),\n"
            . "last_loan AS (\n"
            . "  SELECT DISTINCT ON (r_client_id) r_client_id, r_loan_id, loan_no, status\n"
            . "  FROM r_loan\n"
            . "  ORDER BY r_client_id, r_loan_id DESC\n"
            . "),\n"
            . "last_app AS (\n"
            . "  SELECT DISTINCT ON (r_client_id) r_client_id, r_application_id, product\n"
            . "  FROM r_application\n"
            . "  ORDER BY r_client_id, r_application_id DESC\n"
            . ")\n"
            . "SELECT " . implode(', ', $fields) . "\n"
            . "FROM base_clients cl\n"
            . "LEFT JOIN last_loan l ON l.r_client_id = cl.r_client_id\n"
            . "LEFT JOIN last_app a ON a.r_client_id = cl.r_client_id\n"
            . "ORDER BY cl.$sort $order\n"
            . "LIMIT :limit OFFSET :offset";

        $statement = $this->pdo->prepare($query);
        $statement->execute(array_merge($execute, array('limit' => $limit, 'offset' => $offset)));

        $items = $statement->fetchAll(\PDO::FETCH_ASSOC);

        if (!empty($params['only_ids'])) {
            return array('items' => array_column($items, 'clientId'));
        }

        return array('items' => $items);
    }
