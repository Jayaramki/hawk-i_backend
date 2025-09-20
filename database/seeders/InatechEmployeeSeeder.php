<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\InatechEmployee;

class InatechEmployeeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $employees = [
            ['ina_emp_id' => '539', 'employee_name' => 'Venkateshwaran Krishnamurthy', 'status' => 'active'],
            ['ina_emp_id' => '546', 'employee_name' => 'Ramachandran K', 'status' => 'active'],
            ['ina_emp_id' => '593', 'employee_name' => 'Ajayan K', 'status' => 'active'],
            ['ina_emp_id' => '597', 'employee_name' => 'Ahamed Monzoor P L', 'status' => 'active'],
            ['ina_emp_id' => '599', 'employee_name' => 'Babu Aravinth M', 'status' => 'active'],
            ['ina_emp_id' => '607', 'employee_name' => 'Venkataramanan M', 'status' => 'active'],
            ['ina_emp_id' => '620', 'employee_name' => 'Anitha K', 'status' => 'active'],
            ['ina_emp_id' => '678', 'employee_name' => 'Pushparaj Vincent', 'status' => 'active'],
            ['ina_emp_id' => '682', 'employee_name' => 'Meygnanam P', 'status' => 'active'],
            ['ina_emp_id' => '753', 'employee_name' => 'Ramesh Raja R V', 'status' => 'active'],
            ['ina_emp_id' => '789', 'employee_name' => 'V V P B Gupta Balabhadra', 'status' => 'active'],
            ['ina_emp_id' => '801', 'employee_name' => 'Mathangi R', 'status' => 'active'],
            ['ina_emp_id' => '840', 'employee_name' => 'Dinesh Kumar K', 'status' => 'active'],
            ['ina_emp_id' => '877', 'employee_name' => 'Kankeyan J', 'status' => 'active'],
            ['ina_emp_id' => '888', 'employee_name' => 'R Vishnu Karthik', 'status' => 'active'],
            ['ina_emp_id' => '894', 'employee_name' => 'Mahavidhya Rengaraj', 'status' => 'active'],
            ['ina_emp_id' => '901', 'employee_name' => 'Ananda Jayabalan', 'status' => 'active'],
            ['ina_emp_id' => '917', 'employee_name' => 'Prabhu B', 'status' => 'active'],
            ['ina_emp_id' => '919', 'employee_name' => 'L Nagammai', 'status' => 'active'],
            ['ina_emp_id' => '925', 'employee_name' => 'Balakrishnan Krishnabose', 'status' => 'active'],
            ['ina_emp_id' => '945', 'employee_name' => 'Keerthivasan G', 'status' => 'active'],
            ['ina_emp_id' => '952', 'employee_name' => 'Saranya V', 'status' => 'active'],
            ['ina_emp_id' => '954', 'employee_name' => 'Hariharan C M', 'status' => 'active'],
            ['ina_emp_id' => '957', 'employee_name' => 'Yohananth S', 'status' => 'active'],
            ['ina_emp_id' => '959', 'employee_name' => 'Vignesh S', 'status' => 'active'],
            ['ina_emp_id' => '965', 'employee_name' => 'Rebbakkah M', 'status' => 'active'],
            ['ina_emp_id' => '970', 'employee_name' => 'Shruti Choudhary', 'status' => 'active'],
            ['ina_emp_id' => '972', 'employee_name' => 'Pavithra', 'status' => 'active'],
            ['ina_emp_id' => '973', 'employee_name' => 'Nareshkumar Chandrasekaran', 'status' => 'active'],
            ['ina_emp_id' => '980', 'employee_name' => 'Prakash K', 'status' => 'active'],
            ['ina_emp_id' => '988', 'employee_name' => 'Castle Fin J', 'status' => 'active'],
            ['ina_emp_id' => '1007', 'employee_name' => 'Ranjith Kumar S', 'status' => 'active'],
            ['ina_emp_id' => '1013', 'employee_name' => 'Herbert Albert', 'status' => 'active'],
            ['ina_emp_id' => '1014', 'employee_name' => 'Rajapandian Soundrapandian', 'status' => 'active'],
            ['ina_emp_id' => '1021', 'employee_name' => 'Chakradhar Rao Pashumarty', 'status' => 'active'],
            ['ina_emp_id' => '1030', 'employee_name' => 'Prabaharan P', 'status' => 'active'],
            ['ina_emp_id' => '1033', 'employee_name' => 'Prathiban T', 'status' => 'active'],
            ['ina_emp_id' => '1034', 'employee_name' => 'Ram Kumar R', 'status' => 'active'],
            ['ina_emp_id' => '1037', 'employee_name' => 'Utkarsh De', 'status' => 'active'],
            ['ina_emp_id' => '1038', 'employee_name' => 'Naveenkumar M', 'status' => 'active'],
            ['ina_emp_id' => '1060', 'employee_name' => 'Ramya Rajaram', 'status' => 'active'],
            ['ina_emp_id' => '1065', 'employee_name' => 'Nagarajan M', 'status' => 'active'],
            ['ina_emp_id' => '1073', 'employee_name' => 'Sribalan A R', 'status' => 'active'],
            ['ina_emp_id' => '1074', 'employee_name' => 'Selvaraj D', 'status' => 'active'],
            ['ina_emp_id' => '1077', 'employee_name' => 'Vidyullatha Kataru', 'status' => 'active'],
            ['ina_emp_id' => '1081', 'employee_name' => 'Suresh Gm', 'status' => 'active'],
            ['ina_emp_id' => '1082', 'employee_name' => 'Boomica Pannerselvam', 'status' => 'active'],
            ['ina_emp_id' => '1083', 'employee_name' => 'Vigneshwar S J', 'status' => 'active'],
            ['ina_emp_id' => '1084', 'employee_name' => 'Prathibha Sushma N V', 'status' => 'active'],
            ['ina_emp_id' => '1087', 'employee_name' => 'A Dhanalakshmi', 'status' => 'active'],
            ['ina_emp_id' => '1099', 'employee_name' => 'Arun A', 'status' => 'active'],
            ['ina_emp_id' => '1101', 'employee_name' => 'Jayaramki Chandrasekaran', 'status' => 'active'],
            ['ina_emp_id' => '1106', 'employee_name' => 'Arunachalam Chockalingam', 'status' => 'active'],
            ['ina_emp_id' => '1108', 'employee_name' => 'Angel Gnana Deepam J', 'status' => 'active'],
            ['ina_emp_id' => '1113', 'employee_name' => 'Guruprasad Ravichandran', 'status' => 'active'],
            ['ina_emp_id' => '1115', 'employee_name' => 'Anbuvennila Anbazhagan', 'status' => 'active'],
            ['ina_emp_id' => '1116', 'employee_name' => 'Gopi D', 'status' => 'active'],
            ['ina_emp_id' => '1120', 'employee_name' => 'Prabhakaran Ravichandran', 'status' => 'active'],
            ['ina_emp_id' => '1122', 'employee_name' => 'Anand Paramanandharaj', 'status' => 'active'],
            ['ina_emp_id' => '1124', 'employee_name' => 'Agasi Christopher J', 'status' => 'active'],
            ['ina_emp_id' => '1125', 'employee_name' => 'Thilepan Kadarkarai', 'status' => 'active'],
            ['ina_emp_id' => '1126', 'employee_name' => 'Affra H', 'status' => 'active'],
            ['ina_emp_id' => '1128', 'employee_name' => 'Angshuman Paul', 'status' => 'active'],
            ['ina_emp_id' => '1132', 'employee_name' => 'Yoganandh Udayakumar', 'status' => 'active'],
            ['ina_emp_id' => '1133', 'employee_name' => 'Santhosh Raj R', 'status' => 'active'],
            ['ina_emp_id' => '1134', 'employee_name' => 'Hanumanthu Sudharsana Rao', 'status' => 'active'],
            ['ina_emp_id' => '1138', 'employee_name' => 'Kamaraj Sadhasivam', 'status' => 'active'],
            ['ina_emp_id' => '1140', 'employee_name' => 'Tania Joseph', 'status' => 'active'],
            ['ina_emp_id' => '1147', 'employee_name' => 'Kalakriti.K', 'status' => 'active'],
            ['ina_emp_id' => '1148', 'employee_name' => 'Rishab.S.Mardia', 'status' => 'active'],
            ['ina_emp_id' => '1150', 'employee_name' => 'Vignesh Ram S', 'status' => 'active'],
            ['ina_emp_id' => '1151', 'employee_name' => 'D Bhavya', 'status' => 'active'],
            ['ina_emp_id' => '1153', 'employee_name' => 'Guganesh Ramesh', 'status' => 'active'],
            ['ina_emp_id' => '1157', 'employee_name' => 'Prakash A', 'status' => 'active'],
            ['ina_emp_id' => '1159', 'employee_name' => 'Idhayan A', 'status' => 'active'],
            ['ina_emp_id' => '1167', 'employee_name' => 'Konka Bala Vinod Kumar', 'status' => 'active'],
            ['ina_emp_id' => '1168', 'employee_name' => 'Hariharan Vimalraj', 'status' => 'active'],
            ['ina_emp_id' => '1169', 'employee_name' => 'Shenbagam Durai', 'status' => 'active'],
            ['ina_emp_id' => '1175', 'employee_name' => 'Suji Priya D.M', 'status' => 'active'],
            ['ina_emp_id' => '1178', 'employee_name' => 'Rabirai Madhavan', 'status' => 'active'],
            ['ina_emp_id' => '1200', 'employee_name' => 'Sivakumar N', 'status' => 'active'],
            ['ina_emp_id' => '1202', 'employee_name' => 'Sairam Gopalakrishnan', 'status' => 'active'],
            ['ina_emp_id' => '1211', 'employee_name' => 'Kishor Thirupal', 'status' => 'active'],
            ['ina_emp_id' => '1226', 'employee_name' => 'Mano Ranjini Krithiga Chanthra Sekar', 'status' => 'active'],
            ['ina_emp_id' => '1246', 'employee_name' => 'Rajaram P', 'status' => 'active'],
            ['ina_emp_id' => '1248', 'employee_name' => 'Raju John', 'status' => 'active'],
            ['ina_emp_id' => '1250', 'employee_name' => 'Rohan Baker Fenn', 'status' => 'active'],
            ['ina_emp_id' => '1254', 'employee_name' => 'Nijamudeen Thanathi Mohamed Safi', 'status' => 'active'],
            ['ina_emp_id' => '1259', 'employee_name' => 'Nivetha Veerakumar', 'status' => 'active'],
            ['ina_emp_id' => '1266', 'employee_name' => 'Inumarthi Madhuri', 'status' => 'active'],
        ];

        foreach ($employees as $employee) {
            InatechEmployee::create($employee);
        }
    }
}