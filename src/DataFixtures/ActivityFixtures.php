<?php

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\Persistence\ObjectManager;

class ActivityFixtures extends Fixture
{
    public function load(ObjectManager $manager)
    {
        $activity = new Product();
        
        $activity
            ->setName('Linux commands')
            ->setDescription("
            ## 2.1 Command Line Interface
            Most consumer operating systems are designed to shield the user from the “ins and outs” of the CLI. The Linux community is different in that it positively celebrates the CLI for its power, speed, and ability to accomplish a vast array of tasks with a single command line instruction.
            When a user first encounters the CLI, they can find it challenging because it requires memorizing a dizzying amount of commands and their options. However, once a user has learned the structure of how commands are used, where the necessary files and directories are located, and how to navigate the hierarchy of a file system, they can be immensely productive. This capability provides more precise control, greater speed, and the ability to automate tasks more easily through scripting.
            
            Furthermore, by learning the CLI, a user can easily be productive almost instantly on ANY flavor or distribution of Linux, reducing the amount of time needed to familiarize themselves with a system because of variations in a GUI.
            
            ## 2.2 Commands
            
            What is a command? The simplest answer is that a command is a software program that when executed on the command line, performs an action on the computer.
            
            When you consider a command using this definition, you are really considering what happens when you execute a command. When you type in a command, a process is run by the operating system that can read input, manipulate data, and produce output. From this perspective, a command runs a process on the operating system, which then causes the computer to perform a job.
            
            ‌⁠​However, there is another way of looking at what a command is: look at its source. The source is where the command \"comes from\" and there are several different sources of commands within the shell of your CLI:
            
            -   **Internal Commands**: Also called built-in commands, these commands are built-in to the shell itself. A good example is the `cd` (change directory) command as it is part of the Bash shell. When a user types the cd command, the Bash shell is already executing and knows how to interpret that command, requiring no additional programs to be started.
                
            -   **External Commands**: These commands are stored in files that are searched by the shell. If you type the `ls` command, then the shell searches through a predetermined list of directories to try to find a file named `ls` that it can execute. These commands can also be executed by typing the complete path to the command.
                
            -   **Aliases**: An alias can override a built-in command, function, or a command that is found in a file. Aliases can be useful for creating new commands built from existing functions and commands.
                
            -   **Functions**: Functions can also be built using existing commands to either create new commands, override commands built-in to the shell or commands stored in files. Aliases and functions are normally loaded from the initialization files when the shell first starts, discussed later in this section.
            
            ## 2.2.1 External Commands
            
            Commands that are stored in files can be in several forms that you should be aware of. Most commands are written in the C programming language, which is initially stored in a human-readable text file. These text source files are then compiled into computer-readable binary files, which are then distributed as the command files.
            
            Users who are interested in seeing the source code of compiled, GPL licensed software can find it through the sites where it originated. GPL licensed code also compels distributors of the compiled binaries, such as RedHat and Debian, to make the source code available. Often it is found in the distributors’ repositories.
            
            Note 
            It is possible to view available software packages, binary programs that can be installed directly at the command line. Type the following command into the terminal to view the available source packages (source code that can be modified before it’s compiled into binary programs) for the GNU Compiler Collection:
            
            ```bash
            sysadmin@localhost:~$apt-cache search gcc | grep source
            gcc-4.8-source - Source of the GNU Compiler Collection
            gcc-5-source - Source of the GNU Compiler Collection
            gcc-6-source - Source of the GNU Compiler Collection
            gcc-7-source - Source of the GNU Compiler Collection
            gcc-8-source - Source of the GNU Compiler Collection
            gcc-arm-none-eabi-source - GCC cross compiler for ARM Cortex-A/R/M processors (source)
            ```
            The `apt-cache` command allows us to display information from the APT database cache. It is commonly used to find information about programs you wish to install and the components required to make them work.
            
            Although there are a tremendous number of free and open source programs available, quite often the binary code you will need as a Linux administrator won’t exist for the particular distribution you are running. Since open source licensing gives you access to the code for these programs, one of your tasks will be compiling, and sometimes modifying that code into executable programs that can be installed on the systems you manage. The Free Software Foundation (FSF) distributes the GNU Compiler Collection (GCC) to make this process easier. The GCC provides a compiler system (the special programs used to convert source code into usable binary programs) with front ends for many different programming languages. In fact, the FSF doesn’t limit these tools to just Linux. There are versions of the GCC that run on Unix, Windows, MacOS, and many other systems including specific microcontroller environments.
            
            Linux package management will be covered in greater detail later in the course.
            
            
            Note
            Command files can also contain human-readable text in the form of script files. A script file is a collection of commands that is typically executed at the command line.
            
            The ability to create your own script files is a very powerful feature of the CLI. If you have a series of commands that you regularly find yourself typing in order to accomplish some task, then you can easily create a Bash shell script to perform these multiple commands by typing just one command: the name of your script file. You simply need to place these commands into a file and make the file executable.
            
            ## 2.2.2 Aliases
            
            An alias can be used to map longer commands to shorter key sequences. When the shell sees an alias being executed, it substitutes the longer sequence before proceeding to interpret commands.
            
            For example, the command `ls -l` is commonly aliased to `l` or `ll`. Because these smaller commands are easier to type, it becomes faster to run the `ls -l` command line.
            
            To determine what aliases are set on the current shell use the `alias` command:
            
            
            ```bash
            sysadmin@localhost:~$ alias                                             
            alias egrep='egrep --color=auto'                                       
            alias fgrep='fgrep --color=auto'                                        
            alias grep='grep --color=auto'                                          
            alias l='ls -CF'                                                       
            alias la='ls -A'                                                       
            alias ll='ls -alF'                                                     
            alias ls='ls --color=auto'
            ```
            
            The aliases from the previous examples were created by initialization files. These files are designed to make the process of creating aliases automatic.
            
            New aliases can be created using the following format, where `name` is the name to be given the alias and `command` is the command to be executed when the alias is run.
            
            alias name=command
            
            For example, the `cal 2030` command displays the calendar for the year 2030. Suppose you end up running this command often. Instead of executing the full command each time, you can create an alias called `mycal` and run the alias, as demonstrated in the following graphic:
            ```
            sysadmin@localhost:~$alias mycal=\"cal 2019\"                                    
            sysadmin@localhost:~$mycal                                                     
                                        2019                                                
                  January               February               March                        
            Su Mo Tu We Th Fr Sa  Su Mo Tu We Th Fr Sa  Su Mo Tu We Th Fr Sa                
                   1  2  3  4  5                  1  2                  1  2                
             6  7  8  9 10 11 12   3  4  5  6  7  8  9   3  4  5  6  7  8  9                
            13 14 15 16 17 18 19  10 11 12 13 14 15 16  10 11 12 13 14 15 16                
            20 21 22 23 24 25 26  17 18 19 20 21 22 23  17 18 19 20 21 22 23                
            27 28 29 30 31        24 25 26 27 28        24 25 26 27 28 29 30                
                                                        31
            
                  April                  May                   June                        
            Su Mo Tu We Th Fr Sa  Su Mo Tu We Th Fr Sa  Su Mo Tu We Th Fr Sa                
                1  2  3  4  5  6            1  2  3  4                     1                
             7  8  9 10 11 12 13   5  6  7  8  9 10 11   2  3  4  5  6  7  8                
            14 15 16 17 18 19 20  12 13 14 15 16 17 18   9 10 11 12 13 14 15                
            21 22 23 24 25 26 27  19 20 21 22 23 24 25  16 17 18 19 20 21 22                
            28 29 30              26 27 28 29 30 31     23 24 25 26 27 28 29                
                                                        30             
            
                  October               November              December                      
            Su Mo Tu We Th Fr Sa  Su Mo Tu We Th Fr Sa  Su Mo Tu We Th Fr Sa                
                   1  2  3  4  5                  1  2   1  2  3  4  5  6  7                
             6  7  8  9 10 11 12   3  4  5  6  7  8  9   8  9 10 11 12 13 14                
            13 14 15 16 17 18 19  10 11 12 13 14 15 16  15 16 17 18 19 20 21                
            20 21 22 23 24 25 26  17 18 19 20 21 22 23  22 23 24 25 26 27 28                
            27 28 29 30 31        24 25 26 27 28 29 30  29 30 31                            
            ```                                                        
            Aliases created this way only persist while the shell is open. Once the shell is closed, the new aliases are lost. Additionally, each shell has its own aliases, so, aliases created in one shell won’t be available in a new shell that’s opened.")           
            ->setLab(
                $this->getReference(
                    'lab1'
                )
            )
            ->setCourses(NULL)
            ->setNetwork(NULL)
            ->setInternetAllowed(false)
            ->setInterconnected(false)
            ->setAlone(true)
            ->setUsedInGroup(false)
            ->setUsedTogetherInCourse(false)
            ->setLabInstances(NULL)
        ;

        
        $manager->persist($activity);


        $manager->flush();
    }
}
