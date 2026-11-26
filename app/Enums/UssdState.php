<?php

namespace App\Enums;

enum UssdState: string
{
    case SelectBusiness = 'select_business';
    case MainMenu = 'main_menu';
    case CollectPhone = 'collect_phone';
    case CollectAmount = 'collect_amount';
    case CollectConfirm = 'collect_confirm';
    case AdvanceMenu = 'advance_menu';
    case BorrowAmount = 'borrow_amount';
    case BorrowConfirm = 'borrow_confirm';
    case WithdrawAmount = 'withdraw_amount';
    case WithdrawConfirm = 'withdraw_confirm';
}
