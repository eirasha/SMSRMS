Sunflower Massage Service Reservation and Management System (SMSRMS)

Project Overview
The SMSRMS is a web-based platform designed to streamline the operations of the Sunflower Massage Service. It replaces manual booking and payment recording with an automated, role-based system. The system facilitates seamless interactions between three main user roles: Customers, Massagers, and Admin.

Key Features
Role-Based Access Control: Dedicated dashboards for Customers, Massagers, and Administrators.

Booking System: Includes conflict prevention for unavailable time slots and calendar enhancements.

Payment Integration: Seamless integration of online payment gateways with transaction tracking.

Management Modules: Advanced tools for Admin to manage massagers, bookings, and generate system reports.

Feedback System: Built-in module for customer reviews and feedback collection.


Technology Stack

Backend: PHP (Native) 


Database: MySQL (connected via PDO for security) 


Frontend: HTML5, CSS3, JavaScript (ES6/AJAX for asynchronous updates) 


Environment: XAMPP (Apache/MySQL) 


1. Customer Reservation Flow
Dashboard: Log in as a Customer. Show the dashboard stats.

Booking: Click "Reservation section".
Select a massager. 
Select a service.
Demonstrate the real-time calendar slot checking.

Checkout: Proceed to the payment screen.

redirect to online payment gateway

user(customer) can see the payment history section
user(customer) can provide a feedback after each service session

2. Massager site: 

Dashboard: Log in as a Massager. Show the dashboard stats.
Massager can update/adjust availability
Massager can see the their upcoming sechedule filter by day or week or month
Massager can reply the feedback from customer.


3. Admin site:

Dashboard: Log in as Admin. Show the dashboard stats.
admin can manage the reservartion: able to view the status of payment. admin can update session wether completed, cancelled or pending.

manage massager: admin can add,edit and delete massager profile. 
manage service: admin can add,edit,delete service.
manage payment: able to see total payment and 
manage availability: 
manage feedback: admin can view and reply feedback from customer. 
Generate report: able to generate overall summarise like payment, massager performance, total session that have been completed and total revenue by select range date. Then, the report able to be download as pdf.



