
<aside class="app-sidebar">
  <div class="app-sidebar__user">
    <img class="app-sidebar__user-avatar" src="images/Sahal_logo.jpeg" alt="User Image">
    <div>
      <p class="app-sidebar__user-name">Group Three</p>
      <p class="app-sidebar__user-designation">Restaurant Admin</p>
    </div>
  </div>
  <ul class="app-menu">

    <!-- Dashboard -->
    <li>
      <a class="app-menu__item active" href="index.php">
        <i class="app-menu__icon bi bi-speedometer"></i>
        <span class="app-menu__label">Dashboard</span>
      </a>
    </li>

    <!-- Orders -->
    <li class="treeview">
      <a class="app-menu__item" href="#" data-toggle="treeview">
        <i class="app-menu__icon bi bi-receipt-cutoff"></i>
        <span class="app-menu__label">Orders</span>
        <i class="treeview-indicator bi bi-chevron-right"></i>
      </a>
      <ul class="treeview-menu">
      <li><a class="treeview-item" href="place_order.php"><i class="icon bi bi-circle-fill"></i> place Orders</a></li>
        <li><a class="treeview-item" href="orders.php"><i class="icon bi bi-circle-fill"></i> View Orders</a></li>
        <li><a class="treeview-item" href="cancelled_orders.php"><i class="icon bi bi-circle-fill"></i> Cancelled orders</a></li>
        <li><a class="treeview-item" href="order_history.php"><i class="icon bi bi-circle-fill"></i> Completed order </a></li>
      </ul>
    </li>

    <!-- Menu -->
    <li class="treeview">
      <a class="app-menu__item" href="#" data-toggle="treeview">
        <i class="app-menu__icon bi bi-list"></i>
        <span class="app-menu__label">Menu</span>
        <i class="treeview-indicator bi bi-chevron-right"></i>
      </a>
      <ul class="treeview-menu">
        <li><a class="treeview-item" href="menu.php"><i class="icon bi bi-circle-fill"></i> Manage Menu</a></li>
        <li><a class="treeview-item" href="categories.php"><i class="icon bi bi-circle-fill"></i> Categories</a></li>
      </ul>
    </li>

    <!-- Reservations -->
    <li class="treeview">
      <a class="app-menu__item" href="#" data-toggle="treeview">
        <i class="app-menu__icon bi bi-list"></i>
        <span class="app-menu__label">Reservations</span>
        <i class="treeview-indicator bi bi-chevron-right"></i>
      </a>
      <ul class="treeview-menu">
        <li><a class="treeview-item" href="tables.php"><i class="icon bi bi-circle-fill"></i> Manage Table </a></li>
        <li><a class="treeview-item" href="table_booking.php"><i class="icon bi bi-circle-fill"></i> Table Booking</a></li>

      </ul>
    </li>

    <!-- Customers -->
    <li>
      <a class="app-menu__item" href="customers.php">
        <i class="app-menu__icon bi bi-people"></i>
        <span class="app-menu__label">Customers</span>
      </a>
    </li>

    <!-- Payments -->
    <li class="treeview">
      <a class="app-menu__item" href="#" data-toggle="treeview">
        <i class="app-menu__icon bi bi-credit-card"></i>
        <span class="app-menu__label">Payments</span>
        <i class="treeview-indicator bi bi-chevron-right"></i>
      </a>
      <ul class="treeview-menu">
        <li><a class="treeview-item" href="payments.php"><i class="icon bi bi-circle-fill"></i> Manage Payment</a></li>
      </ul>
    </li>

    <!-- Staff -->
    <li class="treeview">
      <a class="app-menu__item" href="#" data-toggle="treeview">
        <i class="app-menu__icon bi bi-person-badge"></i>
        <span class="app-menu__label">Staff</span>
        <i class="treeview-indicator bi bi-chevron-right"></i>
      </a>
      <ul class="treeview-menu">
        <li><a class="treeview-item" href="employees.php"><i class="icon bi bi-circle-fill"></i> Manage employees</a></li>
        <li><a class="treeview-item" href="attendance.php"><i class="icon bi bi-circle-fill"></i> Employees attendance</a></li>
        <li><a class="treeview-item" href="attendance_report.php"><i class="icon bi bi-circle-fill"></i> Attendance_report</a></li>

      </ul>
    </li>

    <!-- Users -->
<li class="treeview">
  <a class="app-menu__item" href="#" data-toggle="treeview">
    <i class="app-menu__icon bi bi-person-lines-fill"></i>
    <span class="app-menu__label">Users</span>
    <i class="treeview-indicator bi bi-chevron-right"></i>
  </a>
  <ul class="treeview-menu">
    <li><a class="treeview-item" href="manage_users.php"><i class="icon bi bi-circle-fill"></i> manage Users</a></li>
    <li><a class="treeview-item" href="user_roles.php"><i class="icon bi bi-circle-fill"></i> User Roles</a></li>
  </ul>
</li>


    <!-- Reports -->
    <!-- <li>
      <a class="app-menu__item" href="reports.php">
        <i class="app-menu__icon bi bi-bar-chart"></i>
        <span class="app-menu__label">Reports</span>
      </a>
    </li> -->

    <!-- Settings -->
    <li>
      <a class="app-menu__item" href="settings.php">
        <i class="app-menu__icon bi bi-gear"></i>
        <span class="app-menu__label">Settings</span>
      </a>
    </li>

    <!-- Logout -->
    <li>
      <a class="app-menu__item" href="logout.php">
        <i class="app-menu__icon bi bi-box-arrow-right"></i>
        <span class="app-menu__label">Logout</span>
      </a>
    </li>
  </ul>
</aside>
