<?php

return [
    'cluster' => [
        'navigation' => [
            'title' => 'Production Priority',
        ],
    ],

    'criterion' => [
        'navigation' => [
            'title' => 'Criteria & Weights',
        ],

        'form' => [
            'section'     => 'SAW criterion',
            'code'        => 'Code',
            'name'        => 'Criterion name',
            'type'        => 'Type',
            'source'      => 'Data source',
            'weight'      => 'Weight',
            'weight-hint' => 'A number from 0 to 1. The weights of all active criteria must add up to exactly 1.',
            'is-active'   => 'Active',
        ],

        'table' => [
            'code'      => 'Code',
            'name'      => 'Name',
            'type'      => 'Type',
            'source'    => 'Data source',
            'weight'    => 'Weight',
            'is-active' => 'Active',
        ],

        'total' => [
            'valid'   => '✓ Total weight of active criteria: :total. SAW is ready to run.',
            'invalid' => '⚠ Total weight of active criteria: :total. It must be exactly 1 before SAW can run. Use "Normalize weights" to fix it.',
        ],

        'actions' => [
            'normalize' => [
                'label'       => 'Normalize weights',
                'description' => 'The weights of all active criteria are scaled proportionally so they add up to exactly 1.',
                'success'     => 'Weights normalized.',
                'failed'      => 'The weights could not be normalized.',
            ],
        ],
    ],

    'evaluation' => [
        'navigation' => [
            'title' => 'SAW Results',
        ],

        'table' => [
            'name'         => 'Evaluation name',
            'evaluated-at' => 'Evaluated at',
            'trigger'      => 'Trigger',
            'order-count'  => 'Orders',
            'evaluator'    => 'Run by',
            'applied'      => 'Applied',
        ],

        'infolist' => [
            'summary'     => 'Evaluation summary',
            'status'      => 'Priority status',
            'applied'     => 'Applied to the top :count orders',
            'not-applied' => 'Not applied',
            'notes'       => 'Notes',

            'tabs' => [
                'ranking'           => 'Score & Ranking',
                'decision-matrix'   => 'Decision Matrix',
                'normalized-matrix' => 'Normalized Matrix',
                'criteria'          => 'Criteria & Weights',
                'warnings'          => 'Warnings',
            ],

            'ranking' => [
                'rank'     => 'Rank',
                'order'    => 'Manufacturing order',
                'product'  => 'Product',
                'score'    => 'Score (V)',
                'priority' => 'Priority',
                'urgent'   => 'Urgent',
            ],

            'warnings' => [
                'order'     => 'Order',
                'criterion' => 'Criterion',
                'reason'    => 'Description',
                'reasons'   => [
                    'missing_value' => 'Missing value, treated as the worst value',
                ],
            ],
        ],

        'actions' => [
            'run' => [
                'label'            => 'Run SAW',
                'heading'          => 'Run SAW evaluation',
                'description'      => 'All confirmed, in progress and to close orders are ranked using the active criteria.',
                'submit'           => 'Run',
                'name'             => 'Evaluation name (optional)',
                'name-placeholder' => 'Filled in automatically with the date and time',
                'notes'            => 'Notes (optional)',
                'failed'           => 'The evaluation could not be run',
                'nothing-to-rank'  => 'There is no open manufacturing order to rank',
                'success'          => 'SAW evaluation finished',
                'success-body'     => ':count orders were ranked.',
            ],

            'apply' => [
                'label'       => 'Apply priority',
                'heading'     => 'Apply priority to manufacturing orders',
                'description' => 'The top ranked orders are marked Urgent. This can be reverted.',
                'top-n'       => 'Number of top orders',
                'reason'      => 'Reason (optional)',
                'success'     => ':count orders marked Urgent',
            ],

            'revert' => [
                'label'       => 'Revert',
                'description' => 'Order priorities go back to what they were before. Orders whose priority was changed by somebody else are left alone.',
                'success'     => 'Priority restored on :count orders',
            ],
        ],
    ],

    'monitoring' => [
        'model-label' => 'Manufacturing order',
        'hours'       => 'h',

        'navigation' => [
            'title' => 'Order Monitoring',
        ],

        'table' => [
            'order'       => 'Order',
            'product'     => 'Product',
            'state'       => 'Status',
            'priority'    => 'Priority',
            'deadline'    => 'Deadline',
            'time-status' => 'Time',
            'progress'    => 'Progress',
            'material'    => 'Materials',
            'remaining'   => 'Work left',
            'urgency'     => 'Urgency',
            'order-value' => 'Order value',
            'saw'         => 'Latest SAW',
        ],

        'filters' => [
            'late' => 'Late orders only',
        ],

        'time' => [
            'no-deadline' => 'No deadline',
            'late'        => ':days days late',
            'left'        => ':days days left',
        ],

        'urgency-levels' => [
            1 => 'Very low',
            2 => 'Low',
            3 => 'Normal',
            4 => 'High',
            5 => 'Very urgent',
        ],

        'actions' => [
            'attributes' => [
                'label'       => 'Set urgency & value',
                'heading'     => 'Order priority attributes',
                'urgency'     => 'Customer urgency level',
                'order-value' => 'Order value',
                'notes'       => 'Notes',
                'success'     => 'Priority attributes saved',
            ],
        ],
    ],

    'dashboard' => [
        'navigation' => [
            'title' => 'Production Dashboard',
        ],

        'stats' => [
            'heading'               => 'Production summary',
            'open-orders'           => 'Open orders',
            'open-orders-hint'      => 'Confirmed, in progress and to close',
            'late'                  => 'Late',
            'late-hint'             => 'Deadline has passed',
            'due-soon'              => 'Due soon',
            'due-soon-hint'         => 'Deadline within :days days',
            'average-progress'      => 'Average progress',
            'average-progress-hint' => 'All open orders',
            'material-short'        => 'Materials incomplete',
            'material-short-hint'   => 'Orders whose materials are not fully reserved',
        ],

        'status-chart' => [
            'heading'     => 'Orders by status',
            'description' => 'Finished orders from the last :days days',
        ],

        'work-center-load' => [
            'heading'     => 'Work center load',
            'description' => 'Work still to do on open orders (not compared with capacity)',
            'dataset'     => 'Hours left',
        ],

        'ranking' => [
            'heading'                 => 'Top :limit priority orders',
            'evaluation'              => 'Latest evaluation: :name (:date)',
            'empty-heading'           => 'No SAW evaluation yet',
            'empty-description'       => 'Run SAW to rank the orders by completion priority.',
            'open-evaluations'        => 'Open the results',
        ],

        'late' => [
            'heading'       => 'Late orders (max. :limit, most overdue first)',
            'days-late'     => 'Late by',
            'days'          => 'days',
            'empty-heading' => 'No late orders',
        ],
    ],
];
