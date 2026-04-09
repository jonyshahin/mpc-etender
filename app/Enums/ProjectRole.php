<?php

namespace App\Enums;

enum ProjectRole: string
{
    case ProjectManager = 'project_manager';
    case ProcurementOfficer = 'procurement_officer';
    case Evaluator = 'evaluator';
    case Viewer = 'viewer';
}
