<?php

namespace App\Authorization;

final class RolePermissions
{
    public const ROLES = [
        'admin' => 'Admin', 'bkad' => 'BKAD', 'examiner' => 'Pemeriksa',
        'team_lead' => 'Ketua Tim', 'technical_controller' => 'Pengendali Teknis',
        'skpd_operator' => 'Operator SKPD',
    ];

    /** These are action grants; resource access must additionally pass a scoped policy. */
    public const GRANTS = [
        'admin' => ['zi.manage', 'zi.template','accounts.manage', 'access.manage', 'periods.manage', 'references.manage', 'audit.technical'],
        'bkad' => ['periods.manage', 'references.manage', 'proposals.view_all', 'verification.perform', 'notes.administrative', 'history.all', 'monitoring.all', 'reports.all'],
        'examiner' => ['zi.review', 'zi.return', 'zi.finalize', 'proposals.view_assigned', 'reviews.write', 'notes.internal', 'notes.resolve', 'history.assigned', 'kkr.draft', 'kkr.view', 'kkr.download', 'monitoring.assigned', 'reports.assigned', 'archives.assigned', 'audit.assigned'],
        'team_lead' => ['proposals.view_assigned', 'assignments.manage', 'notes.send', 'history.assigned', 'kkr.view', 'kkr.download', 'kkr.approve_lead', 'monitoring.assigned', 'reports.assigned', 'archives.assigned', 'audit.assigned'],
        'technical_controller' => ['proposals.view_assigned', 'history.assigned', 'kkr.view', 'kkr.download', 'kkr.approve_technical', 'monitoring.assigned', 'reports.assigned', 'archives.assigned', 'audit.assigned'],
        'skpd_operator' => ['zi.write', 'zi.submit', 'proposals.create', 'proposals.view_own', 'proposals.update_own', 'proposals.submit_own', 'evidence.upload_own', 'notes.respond_own', 'history.own', 'monitoring.own', 'reports.own'],
    ];
}
