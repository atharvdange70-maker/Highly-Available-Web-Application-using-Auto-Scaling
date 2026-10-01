# EduBlitz HA Web Application

**A beginner-friendly project to deploy a highly available web application using AWS EC2, Application Load Balancer (ALB), Launch Template, and Auto Scaling Group.**

**Repository:** [https://github.com/atharvdange70-maker/Highly-Available-Web-Application-using-Auto-Scaling](https://github.com/atharvdange70-maker/Highly-Available-Web-Application-using-Auto-Scaling)

---

## Project Goal

Deploy a web application that demonstrates **High Availability** and **Auto Scaling**. When students click the "Increase Load" button, it generates CPU load on the EC2 instance, triggering Auto Scaling to automatically launch new instances.

---

## Quick Start (Full Flow)

| Step | Section | Action |
|------|---------|--------|
| 1 | [Clone Repository](#section-0-clone-the-repository) | Clone the project to your local machine |
| 2 | [Security Groups](#section-1-create-security-groups) | Create Load Balancer and EC2 security groups |
| 3 | [Test Instance](#section-2-launch-ec2-instance-for-testing) | Launch a single EC2 instance to verify the app |
| 4 | [IAM Role](#section-25-create-iam-role-required-for-all-instances-listing) | Create IAM role for "All Instances" listing (optional) |
| 5 | [Launch Template](#section-3-create-launch-template) | Create Launch Template with User Data |
| 6 | [Target Group](#section-4-create-target-group) | Create Target Group for the Load Balancer |
| 7 | [Load Balancer](#section-5-create-application-load-balancer) | Create Application Load Balancer |
| 8 | [Auto Scaling Group](#section-6-create-auto-scaling-group) | Create Auto Scaling Group |
| 9 | [Scaling Policy](#section-7-configure-scaling-policy) | Configure CPU-based scaling policy |
| 10 | [Test](#section-8-test-auto-scaling) | Click "Increase Load" and verify Auto Scaling |

---

## Architecture

```
                    ┌─────────────────────┐
                    │       USER          │
                    └──────────┬──────────┘
                               │
                               ▼
                    ┌─────────────────────┐
                    │ Application Load    │
                    │ Balancer (ALB)      │
                    └──────────┬──────────┘
                               │
              ┌────────────────┼────────────────┐
              │                │                │
              ▼                ▼                ▼
     ┌─────────────┐  ┌─────────────┐  ┌─────────────┐
     │ EC2         │  │ EC2         │  │ EC2         │
     │ Instance 1  │  │ Instance 2  │  │ Instance 3  │
     │ (Auto Scale)│  │ (Auto Scale)│  │ (Auto Scale)│
     └─────────────┘  └─────────────┘  └─────────────┘
```

**Flow:**
1. User visits the Load Balancer DNS
2. ALB distributes traffic across EC2 instances
3. Page shows **all running instances** in the Auto Scaling Group
4. When "Increase Load" is clicked → CPU increases to **~80% on ALL instances**
5. Auto Scaling detects high CPU (> 50%)
6. New EC2 instance launches automatically
7. Refresh the page → You may see a different Instance ID (load balancing in action!)

---

## Project Structure

```
edublitz-Highly-Available-Web-Application-using-Auto-Scaling-and-Load-Balancer/
│
├── app/
│   ├── index.html        # Main webpage with metadata, all instances list, and button
│   ├── load.sh           # CPU load generator (~80% target via stress/stress-ng)
│   ├── load.php          # Orchestrator: triggers load on ALL instances
│   ├── load-internal.php # Internal endpoint: runs load on this instance only
│   ├── metadata.php      # Returns instance ID and Availability Zone
│   ├── instances.php     # Returns all instances in ASG (requires IAM role)
│   └── install.sh        # Installation script
│
└── README.md
```

---

## Prerequisites

- **Git** installed on your local machine
- **AWS Account**
- Basic understanding of EC2 and AWS Console
- Region: Choose one (e.g., us-east-1)

---

# SECTION 0: Clone the Repository

Clone the project to your local machine. You will use the `app/` folder contents for deployment.

```bash
git clone https://github.com/atulyw/edublitz-Highly-Available-Web-Application-using-Auto-Scaling-and-Load-Balancer.git
cd edublitz-Highly-Available-Web-Application-using-Auto-Scaling-and-Load-Balancer
```

**Verify the structure:**
```bash
ls -la app/
# You should see: index.html, load.sh, load.php, load-internal.php, metadata.php, instances.php, install.sh
```

Keep this terminal open. You will reference the `app/` folder in the following sections.

---

# SECTION 1: Create Security Groups

## 1.1 Load Balancer Security Group

1. Go to **EC2** → **Security Groups** → **Create security group**
2. **Name:** `edublitz-alb-sg`
3. **Description:** Security group for Application Load Balancer
4. **VPC:** Default VPC (or your VPC)
5. **Inbound rules:**
   | Type | Port | Source |
   |------|------|--------|
   | HTTP | 80   | 0.0.0.0/0 (Anywhere) |

6. Click **Create security group**

---

## 1.2 EC2 Security Group

1. Go to **EC2** → **Security Groups** → **Create security group**
2. **Name:** `edublitz-ec2-sg`
3. **Description:** Security group for EC2 instances
4. **VPC:** Same as above
5. **Inbound rules:**
   | Type | Port | Source |
   |------|------|--------|
   | HTTP | 80   | 0.0.0.0/0 |
   | SSH  | 22   | My IP (recommended) or 0.0.0.0/0 |
   | HTTP | 80   | Same SG (edublitz-ec2-sg) — for instances to call each other's /load-internal |

   **Tip:** If you use 0.0.0.0/0 for HTTP, instances can already reach each other. Adding "Same SG" as source is more secure.

6. Click **Create security group**

---

# SECTION 2: Launch EC2 Instance for Testing

**First, test the application on a single instance before setting up Auto Scaling.**

1. Go to **EC2** → **Launch instance**
2. **Name:** `edublitz-test`
3. **AMI:** Amazon Linux 2
4. **Instance type:** t2.micro
5. **Key pair:** Create new or select existing (needed for SSH)
6. **Network settings:**
   - Security group: Select `edublitz-ec2-sg`
7. **Advanced details** → **User data** — choose ONE of the options below:

**Option A - Clone and run (recommended):**

Paste this into User data. It clones the repo and runs `install.sh` automatically:

```bash
#!/bin/bash
yum install -y git
git clone https://github.com/atulyw/edublitz-Highly-Available-Web-Application-using-Auto-Scaling-and-Load-Balancer.git /tmp/edublitz
cd /tmp/edublitz/app && chmod +x install.sh && sudo bash install.sh
```

**Option B - Copy install.sh to User Data (no Git):**

- Open `app/install.sh` from your cloned repo
- Copy the **entire contents** and paste into User Data

**Option C - Run manually after launch:**

- SSH into the instance
- Run: `git clone https://github.com/atulyw/edublitz-Highly-Available-Web-Application-using-Auto-Scaling-and-Load-Balancer.git /tmp/edublitz`
- Run: `cd /tmp/edublitz/app && chmod +x install.sh && sudo bash install.sh`

8. Click **Launch instance**
9. Wait 2–3 minutes for installation to complete
10. Open **http://\<instance-public-ip\>/** in your browser
11. Verify: You see Instance ID, Availability Zone, and "Increase Load" button
12. Click **Increase Load** → Wait 30 seconds → Refresh. The page should still work.

**Once verified, you can terminate this test instance and proceed to Section 3.**

---

# SECTION 2.5: Create IAM Role (Required for "All Instances" Listing)

**Skip this if you only want the basic demo.** The "All Running Instances" list requires an IAM role so EC2 can call AWS APIs.

1. Go to **IAM** → **Roles** → **Create role**
2. **Trusted entity:** AWS service → **EC2**
3. Click **Next**
4. Attach policy: **AmazonEC2ReadOnlyAccess** (or create custom policy below)
5. **Role name:** `edublitz-ec2-role`
6. Click **Create role**

**Custom policy (minimal permissions):** Create inline policy with:

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Effect": "Allow",
      "Action": [
        "autoscaling:DescribeAutoScalingInstances",
        "ec2:DescribeInstances",
        "ec2:DescribeTags"
      ],
      "Resource": "*"
    }
  ]
}
```

7. Go to **EC2** → **Launch Templates** → Edit `edublitz-launch-template` → **IAM instance profile:** Select `edublitz-ec2-role`

**Note:** Without this role, the page will show only the current instance. "Increase Load" will still work on all instances if they can reach each other via private IP.

---

# SECTION 3: Create Launch Template

1. Go to **EC2** → **Launch Templates** → **Create launch template**
2. **Name:** `edublitz-launch-template`
3. **Description:** Template for EduBlitz HA web application
4. **AMI:** Amazon Linux 2
5. **Instance type:** t2.micro
6. **Key pair:** Select your key pair (for SSH access if needed)
7. **IAM instance profile:** Select `edublitz-ec2-role` (see Section 2.5)
8. **Network settings:**
   - Create security group or select existing
   - Select `edublitz-ec2-sg`
9. **Advanced details** → **User data** — choose ONE:

   **Option A - Clone and run (recommended):**
   ```bash
   #!/bin/bash
   yum install -y git
   git clone https://github.com/atulyw/edublitz-Highly-Available-Web-Application-using-Auto-Scaling-and-Load-Balancer.git /tmp/edublitz
   cd /tmp/edublitz/app && chmod +x install.sh && sudo bash install.sh
   ```

   **Option B - Paste install.sh:**
   - Copy the **entire contents** of `app/install.sh` from your cloned repo and paste into User Data

10. Click **Create launch template**

---

# SECTION 4: Create Target Group

1. Go to **EC2** → **Target Groups** (under Load Balancing) → **Create target group**
2. **Target type:** Instances
3. **Target group name:** `edublitz-tg`
4. **Protocol:** HTTP
5. **Port:** 80
6. **VPC:** Your default VPC
7. **Health check:**
   - Protocol: HTTP
   - Path: `/`
   - Advanced: Default values are fine
8. Click **Next** → **Create target group**

---

# SECTION 5: Create Application Load Balancer

1. Go to **EC2** → **Load Balancers** → **Create load balancer**
2. Select **Application Load Balancer**
3. **Name:** `edublitz-alb`
4. **Scheme:** Internet-facing
5. **Network mapping:**
   - VPC: Default VPC
   - **Select at least 2 Availability Zones** (e.g., us-east-1a, us-east-1b)
6. **Security groups:** Select `edublitz-alb-sg`
7. **Listeners and routing:**
   - Protocol: HTTP, Port: 80
   - Default action: Forward to `edublitz-tg`
8. Click **Create load balancer**
9. Note the **DNS name** of the ALB (e.g., `edublitz-alb-123456789.us-east-1.elb.amazonaws.com`)

---

# SECTION 6: Create Auto Scaling Group

1. Go to **EC2** → **Auto Scaling Groups** → **Create Auto Scaling group**
2. **Name:** `edublitz-asg`
3. **Launch template:** Select `edublitz-launch-template`
4. **Network:**
   - VPC: Default VPC
   - **Select all Availability Zones** (at least 2) that match your ALB
5. **Load balancing:**
   - Attach to existing load balancer
   - Select **Application Load Balancer**
   - Choose `edublitz-alb`
   - Select target group: `edublitz-tg`
6. **Group size:**
   - Desired capacity: **1**
   - Minimum capacity: **1**
   - Maximum capacity: **3**
7. Click **Next** through optional settings → **Create Auto Scaling group**
8. Wait 2–3 minutes for the first instance to launch and pass health checks

---

# SECTION 7: Configure Scaling Policy

1. Go to **EC2** → **Auto Scaling Groups** → Select `edublitz-asg`
2. Click the **Scaling** tab (or **Automatic scaling**)
3. Click **Create scaling policy** (or **Add policy**)
4. **Policy type:** Target tracking scaling
5. **Policy name:** `edublitz-cpu-policy`
6. **Metric type:** CPU Utilization
7. **Target value:** `50` (percent)
8. Click **Create**

**What this means:**
- If average CPU usage across the group goes **above 50%** → Auto Scaling launches a new instance
- If CPU drops **below 50%** → Auto Scaling may terminate an instance (after cooldown)

---

# SECTION 8: Test Auto Scaling

1. Open the **Load Balancer DNS** in your browser  
   Example: `http://edublitz-alb-123456789.us-east-1.elb.amazonaws.com`
2. You should see the EduBlitz HA page with Instance ID and Availability Zone
3. Click **Increase Load**
4. Wait **2–3 minutes**
5. Go to **EC2** → **Auto Scaling Groups** → `edublitz-asg` → **Activity** tab
6. You should see: **"Launching a new EC2 instance"** due to CPU scaling
7. Go to **EC2** → **Instances** → You should see **2 instances** (or more) running

---

# SECTION 9: Verify Load Balancing

1. Keep the Load Balancer URL open in your browser
2. **Refresh the page** multiple times
3. **Instance ID** and **Availability Zone** may **change** on each refresh
4. This proves the ALB is distributing traffic across multiple instances

---

# SECTION 10: Architecture Explanation (Simple)

| Component | Role |
|-----------|------|
| **User** | Accesses the website via browser |
| **Application Load Balancer** | Receives requests and sends them to healthy EC2 instances |
| **EC2 Instances** | Run the web application (nginx + PHP + stress) |
| **Auto Scaling Group** | Monitors CPU and adds/removes instances automatically |
| **Launch Template** | Defines how new instances are configured (AMI, type, User Data) |
| **Target Group** | Tells ALB which instances are healthy and ready for traffic |

**When you click "Increase Load":**
1. The `stress` command runs on the EC2 instance
2. CPU usage goes up
3. CloudWatch reports high CPU to Auto Scaling
4. Auto Scaling launches a new instance
5. New instance gets added to Target Group
6. ALB starts sending traffic to the new instance too

---

# SECTION 11: Learning Outcomes

After completing this project, students will understand:

- How **Application Load Balancer** distributes traffic
- How **Auto Scaling** responds to CPU usage
- How **Launch Template** standardizes instance configuration
- How **User Data** automates installation on new instances
- Basic **High Availability** architecture on AWS
- **Target Tracking** scaling policies

---

# SECTION 12: Cleanup Steps

**To avoid charges, delete resources in this order:**

1. **Auto Scaling Group**
   - EC2 → Auto Scaling Groups → Select `edublitz-asg` → Delete
   - Set capacity to 0 if prompted (instances will terminate)

2. **Load Balancer**
   - EC2 → Load Balancers → Select `edublitz-alb` → Delete

3. **Target Group**
   - EC2 → Target Groups → Select `edublitz-tg` → Delete

4. **Launch Template**
   - EC2 → Launch Templates → Select `edublitz-launch-template` → Actions → Delete

5. **EC2 Instances**
   - Terminate any remaining instances (if not already terminated by ASG)

6. **IAM Role** (if created)
   - IAM → Roles → Delete `edublitz-ec2-role` (after Launch Template is deleted)

7. **Security Groups**
   - Delete `edublitz-alb-sg` and `edublitz-ec2-sg` (after instances and ALB are gone)

---

## Troubleshooting

| Problem | Solution |
|---------|----------|
| Page doesn't load | Check security groups allow HTTP (80) from 0.0.0.0/0 |
| Instance ID shows "Error" | Ensure metadata.php is accessible; check PHP-FPM is running |
| Increase Load does nothing | Check load.sh is executable; verify stress is installed |
| Auto Scaling doesn't launch | Wait 3–5 min; CPU must stay above 50% for a few minutes |
| Health check failing | Ensure nginx serves `/` correctly; check Target Group health |

---

## Repository

- **GitHub:** [https://github.com/atharvdange70-maker/Highly-Available-Web-Application-using-Auto-Scaling](https://github.com/atharvdange70-maker/Highly-Available-Web-Application-using-Auto-Scaling)
- **Clone:** `https://github.com/atharvdange70-maker/Highly-Available-Web-Application-using-Auto-Scaling.git`
