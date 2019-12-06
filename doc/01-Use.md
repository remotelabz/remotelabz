# Groupes

## Architecture

Un groupe est un agrégat d'utilisateurs sans limite numérique. Chaque groupe possède un identifiant ainsi qu'un chemin URL unique, qui permet de l'identifier. Chaque groupe possède un parent. Si le parent d'un groupe est nul, ce groupe est un *groupe racine*. Si le parent est une valeur valide, le groupe est un *sous-groupe*.

# Activités

## Polices d'activité & d'instances

Lexique :
- Accéder (à une instance) : Voir l'instance, afficher ses propriétés et afficher la console à distance.
- Commander (une instance) : Démarrer, arrêter et redémarrer une instance.

1. Un utilisateur peut démarrer à titre individuel une instance d'une activité à laquelle il a accès.
2. Un utilisateur peut démarrer pour son groupe une instance d'une activité à laquelle il a accès s'il est propriétaire ou administrateur de ce groupe.
3. Un utilisateur ne peut avoir qu'une seule instance par activité.
4. Un groupe ne peut avoir qu'une seule instance par activité.
5. Un utilisateur peut accéder à une instance de groupe s'il est membre de ce groupe.
6. Un utilisateur appartenant à un groupe ne peut pas accéder aux instances d'un groupe enfant s'il n'en est pas membre.
7. Un enseignant peut accéder et commander toutes les instances d'un groupe dont il est propriétaire et ses sous-groupes.
8. Un administrateur peut accéder et commander toutes les instances sans distinction.

## Cas d'utilisation

### Cas 1 : Activité effectuée individuellement

Acteurs :
- Enseignant
- Etudiant

L'enseignant souhaite faire réaliser une activité à sa classe de façon individuelle.

1. L'enseignant crée une activité qu'il associe à un lab et à son groupe **MathInfo101**. 
2. L'enseignant ajoute l'étudiant au groupe.
3. L'étudiant accède à l'activité puis la démarre et peut accéder à son instance.

### Cas 2 : Activité effectuée en groupe

Acteurs :
- Enseignant
- Etudiants (2+)

L'enseignant souhaite faire réaliser à ses élèves une activité collaborative divisée en plusieurs groupes parmi sa classe.

1. L'enseignant crée une activité qu'il associe à un lab et à son groupe **MathInfo101**.
2. L'enseignant ajoute les étudiants au groupe.
3. Les étudiants créent deux nouveaux groupes **Groupe A** et **Groupe B** dans le groupe **MathInfo101** puis se répartissent équitablement dans ces deux groupes.
4. Le créateur de chaque groupe (ou un administrateur désigné dans chaque groupe) lance une instance pour le groupe.
5. Les étudiants de chaque groupe peuvent accéder à l'instance de leur groupe respectif.
6. En parallèle, chaque étudiant peut lancer une instance équivalente à titre personnel pour procéder à des tests qui n'impacteront pas le groupe. 
7. A tout moment, l'enseignant peut accéder et contrôler les instances de **Groupe A** et **Groupe B** puisque ceux-ci sont des sous-groupes de **MathInfo101** dont l'enseignant est propriétaire.
8. Malgré leur appartenance à **MathInfo101** qui contient **Groupe B**, les utilisateurs de **Groupe A** ne peuvent pas accéder à l'instance de **Groupe B** puisqu'ils n'en sont pas membres.

### Cas 2 : Activité effectuée en classe entière

Acteurs :
- Enseignant
- Etudiants (2+)

Ce scénario est sensiblement le même que le [Cas 2](#cas-2). La différence réside dans le point 3. : aucun sous-groupe n'est créé et que l'instance est associée cette fois au groupe **MathInfo101**. Tout le groupe peut donc y accéder.