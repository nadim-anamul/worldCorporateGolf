<?php

declare(strict_types=1);

class RegistrationValidator
{
  public function validate(array $input, string $regType): array
  {
    if (!in_array($regType, ['golfer', 'non_golfer'], true)) {
      throw new RuntimeException('Invalid registration type.');
    }

    $data = [
      'player_category'   => 'N/A',
      'reference_name'    => '',
      'reference_mission' => '',
      'reference_contact' => '',
      'full_name'         => sanitizeInput((string)($input['fullName'] ?? '')),
      'designation'       => sanitizeInput((string)($input['designation'] ?? '')),
      'organization'      => sanitizeInput((string)($input['organization'] ?? '')),
      'nationality'       => sanitizeInput((string)($input['nationality'] ?? '')),
      'contact'           => sanitizeInput((string)($input['contact'] ?? '')),
      'email'             => strtolower(trim(filter_var((string)($input['email'] ?? ''), FILTER_SANITIZE_EMAIL))),
      'mailing_address'   => sanitizeInput((string)($input['mailingAddress'] ?? '')),
      'tshirt_size'       => sanitizeInput((string)($input['tshirtSize'] ?? '')),
      'schedule_group'    => $regType === 'non_golfer'
        ? 'N/A'
        : sanitizeInput((string)($input['scheduleGroup'] ?? '')),
      'name_on_polo'      => sanitizeInput((string)($input['nameOnPolo'] ?? '')),
      'handicap'          => $regType === 'golfer' ? sanitizeInput((string)($input['handicap'] ?? '')) : '',
      'golf_set_brand'    => $regType === 'golfer' ? sanitizeInput((string)($input['golfSetBrand'] ?? '')) : '',
      'putting_contest'   => $regType === 'non_golfer' ? sanitizeInput((string)($input['puttingContestInterest'] ?? '')) : '',
    ];

    $required = [
      'Full Name'    => $data['full_name'],
      'Designation'  => $data['designation'],
      'Organization' => $data['organization'],
      'Nationality'  => $data['nationality'],
      'Contact'      => $data['contact'],
      'Email'        => $data['email'],
      'T-Shirt Size' => $data['tshirt_size'],
      'Name on Polo' => $data['name_on_polo'],
    ];

    if ($regType === 'golfer') {
      $required['Schedule'] = $data['schedule_group'];
      $required['Handicap'] = $data['handicap'];
      $required['Golf Set Brand'] = $data['golf_set_brand'];
    } else {
      $required['Putting Contest Interest'] = $data['putting_contest'];
    }

    foreach ($required as $label => $val) {
      if ($val === '') {
        throw new RuntimeException("Please fill in: {$label}.");
      }
    }

    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
      throw new RuntimeException('Invalid email address.');
    }

    if ($regType === 'non_golfer' && !in_array($data['putting_contest'], ['Yes', 'No'], true)) {
      throw new RuntimeException('Invalid putting contest selection.');
    }

    if (strlen($data['tshirt_size']) > 50) {
      $data['tshirt_size'] = substr($data['tshirt_size'], 0, 50);
    }

    return $data;
  }
}
